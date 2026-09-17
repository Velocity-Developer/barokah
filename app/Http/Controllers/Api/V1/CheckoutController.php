<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BuyerInformationRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\CouponService;
use App\Services\SettingsService;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Direct Buy checkout (spec §11.4/§14.4).
 *
 * Guest checkout is allowed; authenticated orders link user_id. Single
 * product Buy Now uses product_id/quantity; items[] allows one
 * checkout with products from multiple sellers (spec §14.3). Buyer and shipping
 * locations are stored separately; shipping destination drives quote matching. Each item carries
 * seller_id + product snapshots. Stock is reserved on order create inside
 * a DB transaction with pessimistic locks (TBC spec §24 item 10: restore
 * on expiry/failure lands in Task 7). PayNet intent is created separately
 * via POST /api/v1/orders/{n}/payments (Task 8).
 */
class CheckoutController extends Controller
{
    public function __construct(protected SettingsService $settings, protected ShippingService $shipping, protected CouponService $coupons) {}

    /**
     * Create a pending_payment order from a direct Buy payload.
     */
    public function store(BuyerInformationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var array<int, array{product_id: int, quantity: int}> $lines */
        $lines = $validated['items']
            ?? [['product_id' => $validated['product_id'], 'quantity' => $validated['quantity']]];

        $shippingMethod = $validated['shipping_method'] ?? 'fixed';
        $currencyCode = (string) $this->settings->get('currency.code', config('marketplace.currency.code', 'MYR'));
        $expirationMinutes = (int) $this->settings->get('checkout.order_expiration_minutes', config('marketplace.checkout.order_expiration_minutes', 30));

        $order = DB::transaction(function () use ($request, $validated, $lines, $shippingMethod, $currencyCode, $expirationMinutes) {
            $subtotal = 0.0;

            /** @var array<int, array{product: Product, quantity: int, line_total: float}> $prepared */
            $prepared = [];

            foreach ($lines as $line) {
                /** @var Product|null $product */
                $product = Product::query()->whereKey($line['product_id'])->lockForUpdate()->first();

                if ($product === null || $product->status !== ProductStatus::Active) {
                    abort(409, 'Selected product is not available.');
                }

                if ($product->stock < $line['quantity']) {
                    abort(409, 'Insufficient stock for '.$product->name.'.');
                }

                $flashSale = $product->flashSales()
                    ->active()
                    ->lockForUpdate()
                    ->first();

                if ($flashSale !== null && $flashSale->remainingQuantity() < $line['quantity']) {
                    abort(409, 'Insufficient flash sale quota for '.$product->name.'.');
                }

                $unitPrice = (float) ($flashSale?->price ?? $product->price);
                $lineTotal = $unitPrice * $line['quantity'];
                $subtotal += $lineTotal;
                $prepared[] = [
                    'product' => $product,
                    'flash_sale_model' => $flashSale,
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'category_id' => $product->category_id,
                    'flash_sale' => $flashSale !== null,
                ];
            }

            /** @var Order $order */
            // Snapshot the ShippingService quote on the order (spec §16.2);
            // Fixed Rate stays the default provider behavior (spec §16.1).
            $address = [
                'address' => $validated['shipping_address'],
                'state' => $validated['shipping_state'],
                'city' => $validated['shipping_city'] ?? null,
                'post_code' => $validated['shipping_post_code'],
            ];
            $quotes = collect($prepared)->groupBy(fn (array $row): int => $row['product']->seller_id)->map(
                fn ($sellerLines, int $sellerId): array => ['seller_id' => $sellerId, 'quote' => $this->shipping->quote(
                    $address,
                    round((float) $sellerLines->sum('line_total'), 2),
                    $sellerLines->map(fn (array $row): array => [
                        'product_id' => $row['product']->id,
                        'quantity' => $row['quantity'],
                    ])->values()->all(),
                    $shippingMethod,
                )]
            );
            $quote = [
                'method' => $shippingMethod ?: 'fixed',
                'provider' => $quotes->pluck('quote.provider')->filter()->first(),
                'fee' => round((float) $quotes->sum('quote.fee'), 2),
                'breakdown' => $quotes->values()->map(fn (array $item, int $index): array => [
                    'seller_id' => $item['seller_id'],
                    'label' => 'Shipping '.($index + 1),
                    'fee' => $item['quote']['fee'],
                    'provider' => $item['quote']['provider'],
                ])->all(),
            ];
            $couponResult = ($validated['coupon_code'] ?? null) !== null
                ? $this->coupons->calculate($validated['coupon_code'], array_map(fn (array $row): array => [
                    'product_id' => $row['product']->id,
                    'seller_id' => $row['product']->seller_id,
                    'category_id' => $row['category_id'],
                    'line_total' => $row['line_total'],
                    'flash_sale' => $row['flash_sale'],
                ], $prepared), (float) $quote['fee'], $request->user()?->id)
                : null;
            $discount = (float) ($couponResult['discount'] ?? 0);
            $shippingFee = max(0, (float) $quote['fee'] - (float) ($couponResult['shipping_discount'] ?? 0));

            $order = Order::query()->create([
                'order_number' => $this->uniqueOrderNumber(),
                'user_id' => $request->user()?->id,
                'customer_name' => $validated['buyer']['name'],
                'customer_address' => $validated['buyer']['address'],
                'customer_state' => $validated['buyer']['state'],
                'customer_city' => $validated['buyer']['city'] ?? null,
                'customer_post_code' => $validated['buyer']['post_code'],
                'customer_phone' => $validated['buyer']['phone'],
                'customer_email' => $validated['buyer']['email'] ?? null,
                'shipping_address' => $validated['shipping_address'],
                'shipping_state' => $validated['shipping_state'],
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_post_code' => $validated['shipping_post_code'],
                'currency_code' => $currencyCode,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'coupon_code' => $couponResult['coupon']->code ?? null,
                'coupon_snapshot' => $couponResult['snapshot'] ?? null,
                'shipping_fee' => $shippingFee,
                'total' => max(0, $subtotal - $discount + $shippingFee),
                'status' => OrderStatus::PendingPayment,
                'shipping_method' => $quote['method'],
                'expired_at' => now()->addMinutes($expirationMinutes),
            ]);

            foreach ($prepared as $row) {
                /** @var Product $product */
                $product = $row['product'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'product_name_snapshot' => $product->name,
                    'product_slug_snapshot' => $product->slug,
                    'price_snapshot' => $row['unit_price'],
                    'quantity' => $row['quantity'],
                    'subtotal' => $row['line_total'],
                    'discount_amount' => $couponResult === null ? 0 : round($row['line_total'] / $subtotal * $discount, 2),
                ]);

                $product->decrement('stock', $row['quantity']);
                if ($row['flash_sale_model'] !== null) {
                    $row['flash_sale_model']->increment('quantity_sold', $row['quantity']);
                }
            }

            if ($couponResult !== null) {
                $coupon = $couponResult['coupon'];
                $coupon->increment('usage_count');
                $coupon->usages()->create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()?->id,
                    'discount_amount' => $discount,
                ]);
            }

            foreach ($quotes as $sellerQuote) {
                $order->sellerTrackings()->create([
                    'seller_id' => $sellerQuote['seller_id'],
                    'shipping_fee' => $sellerQuote['quote']['fee'],
                    'shipping_provider' => $sellerQuote['quote']['provider'],
                ]);
            }

            return $order->load(['items', 'sellerTrackings']);
        });

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * Human-readable order number (TBC spec §24 item 11: pattern
     * BRK-YYYYMMDD-XXXXXX until confirmed).
     */
    protected function uniqueOrderNumber(): string
    {
        do {
            $candidate = 'BRK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
