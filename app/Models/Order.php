<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Minimal order model per spec §10.2/§14. Status enums and payment
 * handling land in Task 7; checkout creation lands in Task 6.
 *
 * @property int $id
 * @property string $order_number
 * @property int|null $user_id
 * @property string $customer_name
 * @property string $customer_address
 * @property string $customer_state
 * @property string|null $customer_city
 * @property string $customer_post_code
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property string|null $shipping_address
 * @property string|null $shipping_state
 * @property string|null $shipping_city
 * @property string|null $shipping_post_code
 * @property string $currency_code
 * @property string $subtotal
 * @property string $shipping_fee
 * @property string $total
 * @property OrderStatus $status
 * @property string $shipping_method
 * @property string|null $shipping_provider
 * @property string|null $notes
 * @property Carbon|null $expired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_number',
    'user_id',
    'customer_name',
    'customer_address',
    'customer_state',
    'customer_city',
    'customer_post_code',
    'customer_phone',
    'customer_email',
    'shipping_address',
    'shipping_state',
    'shipping_city',
    'shipping_post_code',
    'currency_code',
    'subtotal',
    'discount_amount',
    'coupon_code',
    'coupon_snapshot',
    'shipping_fee',
    'total',
    'status',
    'shipping_method',
    'expired_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'coupon_snapshot' => 'array',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => OrderStatus::class,
            'expired_at' => 'datetime',
        ];
    }

    /**
     * Orders still awaiting PayNet settlement (spec §14.2).
     */
    #[Scope]
    protected function pendingPayment(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PendingPayment);
    }

    /**
     * Whether the pending order has passed its checkout expiry.
     */
    public function isExpired(): bool
    {
        if ($this->status !== OrderStatus::PendingPayment) {
            return false;
        }

        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    /**
     * Whether the pending order can still be paid.
     */
    public function isPayable(): bool
    {
        return $this->status === OrderStatus::PendingPayment && ! $this->isExpired();
    }

    /**
     * Eagerly mark this pending order expired in the database when its
     * expired_at has passed. This keeps DB state in sync with what the UI
     * shows via isExpired() and is used as an on-access lazy-expire when
     * a buyer/admin loads the order before the scheduled job runs.
     *
     * Runs the same stock-restore + payment-failed mutations as
     * ExpirePendingOrders (spec §14.5), idempotently wrapped in a
     * transaction with row locking.
     */
    public function markExpiredIfOverdue(): bool
    {
        if (! $this->isExpired()) {
            return false;
        }

        return DB::transaction(function (): bool {
            /** @var Order|null $locked */
            $locked = Order::query()
                ->whereKey($this->id)
                ->where('status', OrderStatus::PendingPayment)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isExpired()) {
                return false;
            }

            $locked->load('items');

            foreach ($locked->items as $item) {
                if ($item->product_id !== null) {
                    Product::query()
                        ->whereKey($item->product_id)
                        ->increment('stock', $item->quantity);
                }
            }

            $locked->update(['status' => OrderStatus::Expired]);
            $locked->payment()
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Expired])
                ->update([
                    'status' => PaymentStatus::Failed,
                    'failed_at' => now(),
                ]);

            // Reflect DB writes on the in-memory instance so callers that
            // serialize status/payment immediately afterwards stay fresh.
            $this->status = OrderStatus::Expired;
            if ($this->payment !== null) {
                $this->payment->status = PaymentStatus::Failed;
                $this->payment->failed_at = now();
            }
            $this->load(['payment']);

            return true;
        });
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sellerTrackings(): HasMany
    {
        return $this->hasMany(OrderSellerTracking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
