<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $coupons = Coupon::query()
            ->where('status', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(function ($query): void {
                $query->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->latest()
            ->get([
                'id', 'code', 'name', 'description', 'discount_type', 'discount_value',
                'maximum_discount', 'minimum_spend', 'ends_at', 'allow_flash_sale',
            ])
            ->map(fn (Coupon $coupon): array => [
                ...$coupon->toArray(),
                'ends_at' => $coupon->ends_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Coupon/Index', ['coupons' => $coupons]);
    }
}
