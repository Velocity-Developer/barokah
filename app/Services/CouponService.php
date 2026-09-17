<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @param  array<int, array{product_id:int, seller_id:int, category_id:int, line_total:float, flash_sale:bool}>  $items
     * @return array{coupon: Coupon, discount: float, shipping_discount: float, eligible_subtotal: float, snapshot: array<string, mixed>}
     */
    public function calculate(string $code, array $items, float $shippingFee = 0, ?int $userId = null): array
    {
        $coupon = Coupon::query()->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
        if ($coupon === null || ! $coupon->isAvailable()) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon is invalid or unavailable.']);
        }

        if ($coupon->per_user_limit !== null && $userId !== null && $coupon->usages()->where('user_id', $userId)->count() >= $coupon->per_user_limit) {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon usage limit reached.']);
        }

        $hasProductScope = $coupon->products()->exists();
        $hasCategoryScope = $coupon->categories()->exists();
        $productIds = $hasProductScope ? $coupon->products()->pluck('products.id')->all() : [];
        $categoryIds = $hasCategoryScope ? $coupon->categories()->pluck('categories.id')->all() : [];

        $eligible = array_filter($items, function (array $item) use ($coupon, $hasProductScope, $hasCategoryScope, $productIds, $categoryIds): bool {
            if ($coupon->owner_type === 'seller' && $item['seller_id'] !== $coupon->seller_id) {
                return false;
            }
            if (! $coupon->allow_flash_sale && $item['flash_sale']) {
                return false;
            }

            return (! $hasProductScope && ! $hasCategoryScope)
                || ($hasProductScope && in_array($item['product_id'], $productIds, true))
                || ($hasCategoryScope && in_array($item['category_id'], $categoryIds, true));
        });
        $eligibleSubtotal = round(array_sum(array_column($eligible, 'line_total')), 2);
        if ($eligibleSubtotal < (float) $coupon->minimum_spend) {
            throw ValidationException::withMessages(['coupon_code' => 'Minimum spend not reached.']);
        }

        $discount = match ($coupon->discount_type) {
            'percentage' => $eligibleSubtotal * ((float) $coupon->discount_value / 100),
            'fixed' => (float) $coupon->discount_value,
            default => 0,
        };
        $discount = min($eligibleSubtotal, $discount);
        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, (float) $coupon->maximum_discount);
        }
        $shippingDiscount = $coupon->discount_type === 'free_shipping' ? $shippingFee : 0;

        return [
            'coupon' => $coupon,
            'discount' => round($discount, 2),
            'shipping_discount' => round($shippingDiscount, 2),
            'eligible_subtotal' => $eligibleSubtotal,
            'snapshot' => $coupon->only(['code', 'name', 'discount_type', 'discount_value', 'maximum_discount', 'owner_type', 'seller_id', 'allow_flash_sale']),
        ];
    }
}
