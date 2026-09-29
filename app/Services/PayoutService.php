<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\OrderSellerTracking;
use App\Models\Seller;
use App\Models\SellerPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Seller earnings and payouts. A store earns from a shipment once the order
 * is paid and the store marked its part Delivered; each shipment is paid out
 * at most once because a request locks it through order_seller_trackings.payout_id.
 */
class PayoutService
{
    /** Order statuses whose money has actually come in. */
    public const PAID_ORDER_STATUSES = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    /** Transfer proofs are private; they are served through PayoutProofController. */
    public const PROOF_DISK = 'local';

    public function __construct(private SettingsService $settings) {}

    /**
     * Platform commission in percent (0-100), taken from product sales only.
     */
    public function commissionRate(): float
    {
        return min(100.0, max(0.0, (float) $this->settings->get('marketplace.commission_rate', 0)));
    }

    /**
     * Delivered, paid shipments of the store that no payout has claimed yet.
     *
     * @return Collection<int, OrderSellerTracking>
     */
    public function availableTrackings(Seller $seller, bool $lock = false): Collection
    {
        return OrderSellerTracking::query()
            ->where('seller_id', $seller->id)
            ->whereNull('payout_id')
            ->where('tracking_status', 'delivered')
            ->whereHas('order', fn (Builder $order) => $order->whereIn('status', self::PAID_ORDER_STATUSES))
            ->with(['order' => fn ($relation) => $relation->with(['items' => fn ($items) => $items->where('seller_id', $seller->id)])])
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->orderBy('delivered_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * What one shipment is worth to its store, before commission.
     *
     * The store only carries a coupon discount when the coupon is its own;
     * marketplace coupons (and other stores' coupons) are absorbed elsewhere.
     *
     * @return array{product: float, shipping: float}
     */
    public function shipmentEarning(OrderSellerTracking $tracking): array
    {
        $order = $tracking->order;
        $items = $order->items->where('seller_id', $tracking->seller_id);
        $product = (float) $items->sum('subtotal');

        $couponSellerId = $order->coupon_snapshot['seller_id'] ?? null;
        if ($couponSellerId !== null && (int) $couponSellerId === (int) $tracking->seller_id) {
            $product -= (float) $order->discount_amount;
        }

        return [
            'product' => round(max(0.0, $product), 2),
            'shipping' => round((float) $tracking->shipping_fee, 2),
        ];
    }

    /**
     * Totals for a set of shipments at the given commission rate.
     *
     * @param  iterable<OrderSellerTracking>  $trackings
     * @return array{product: float, shipping: float, commission: float, net: float, count: int}
     */
    public function totals(iterable $trackings, float $rate): array
    {
        $product = 0.0;
        $shipping = 0.0;
        $count = 0;

        foreach ($trackings as $tracking) {
            $earning = $this->shipmentEarning($tracking);
            $product += $earning['product'];
            $shipping += $earning['shipping'];
            $count++;
        }

        $commission = round($product * $rate / 100, 2);

        return [
            'product' => round($product, 2),
            'shipping' => round($shipping, 2),
            'commission' => $commission,
            'net' => round($product - $commission + $shipping, 2),
            'count' => $count,
        ];
    }

    /**
     * Claim every available shipment of the store in one payout request.
     */
    public function request(Seller $seller, ?string $note): SellerPayout
    {
        $bankAccount = trim((string) $seller->bank_account);

        if ($bankAccount === '') {
            throw ValidationException::withMessages(['payout' => __('Add your bank account in Store settings before requesting a payout.')]);
        }

        return DB::transaction(function () use ($seller, $note, $bankAccount): SellerPayout {
            $trackings = $this->availableTrackings($seller, lock: true);
            $rate = $this->commissionRate();
            $totals = $this->totals($trackings, $rate);

            if ($totals['count'] === 0 || $totals['net'] <= 0) {
                throw ValidationException::withMessages(['payout' => __('There is no balance to withdraw yet.')]);
            }

            $payout = SellerPayout::query()->create([
                'reference' => $this->uniqueReference(),
                'seller_id' => $seller->id,
                'status' => PayoutStatus::Pending,
                'currency_code' => $trackings->first()->order->currency_code,
                'product_amount' => $totals['product'],
                'shipping_amount' => $totals['shipping'],
                'commission_rate' => $rate,
                'commission_amount' => $totals['commission'],
                'net_amount' => $totals['net'],
                'orders_count' => $totals['count'],
                'bank_account' => $bankAccount,
                'seller_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ]);

            OrderSellerTracking::query()->whereKey($trackings->modelKeys())->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }

    public function approve(SellerPayout $payout, User $admin): void
    {
        $this->ensureStatus($payout, [PayoutStatus::Pending]);

        $payout->update([
            'status' => PayoutStatus::Approved,
            'approved_at' => now(),
            'processed_by' => $admin->id,
        ]);
    }

    /**
     * Record the transfer the admin made outside the site.
     */
    public function markPaid(SellerPayout $payout, User $admin, UploadedFile $proof, ?string $transferReference, ?string $note): void
    {
        $this->ensureStatus($payout, [PayoutStatus::Approved]);

        $payout->update([
            'status' => PayoutStatus::Paid,
            'paid_at' => now(),
            'proof_path' => $proof->store('payout-proofs', self::PROOF_DISK),
            'transfer_reference' => $transferReference,
            'admin_note' => $note ?? $payout->admin_note,
            'processed_by' => $admin->id,
        ]);
    }

    /**
     * Rejecting releases the shipments so the store can request them again.
     */
    public function reject(SellerPayout $payout, User $admin, string $reason): void
    {
        $this->ensureStatus($payout, PayoutStatus::open());

        DB::transaction(function () use ($payout, $admin, $reason): void {
            $payout->trackings()->update(['payout_id' => null]);
            $payout->update([
                'status' => PayoutStatus::Rejected,
                'rejected_at' => now(),
                'admin_note' => $reason,
                'processed_by' => $admin->id,
            ]);
        });
    }

    /**
     * @param  list<PayoutStatus>  $allowed
     */
    private function ensureStatus(SellerPayout $payout, array $allowed): void
    {
        if (! in_array($payout->status, $allowed, true)) {
            throw ValidationException::withMessages(['payout' => __('This payout has already been processed.')]);
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (SellerPayout::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
