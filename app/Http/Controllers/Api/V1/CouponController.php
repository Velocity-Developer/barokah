<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CouponPreviewRequest;
use App\Models\Product;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function preview(CouponPreviewRequest $request, CouponService $coupons): JsonResponse
    {
        $data = $request->validated();
        $requestedItems = collect($data['items'])->keyBy('product_id');
        $items = Product::query()->with('flashSales')->whereIn('id', $requestedItems->keys())->get()
            ->map(function (Product $product) use ($requestedItems): array {
                $flashSale = $product->activeFlashSale();
                $unitPrice = (float) ($flashSale?->price ?? $product->price);

                return [
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'category_id' => $product->category_id,
                    'line_total' => $unitPrice * (int) $requestedItems[$product->id]['quantity'],
                    'flash_sale' => $flashSale !== null,
                ];
            })->all();

        return response()->json(DB::transaction(fn (): array => $coupons->calculate(
            $data['coupon_code'],
            $items,
            (float) ($data['shipping_fee'] ?? 0),
            $request->user()?->id,
        )));
    }
}
