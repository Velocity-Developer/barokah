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
        $products = Product::query()->with('flashSales')->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
        // One line per requested item: two variants of a product are two lines.
        $items = collect($data['items'])->map(function (array $item) use ($products): array {
            /** @var Product $product */
            $product = $products[$item['product_id']];
            $variant = $product->resolveVariant(isset($item['variant_id']) ? (int) $item['variant_id'] : null);
            $flashSale = $product->activeFlashSale();

            return [
                'product_id' => $product->id,
                'seller_id' => $product->seller_id,
                'category_id' => $product->category_id,
                'line_total' => $product->priceFor($variant, $flashSale) * (int) $item['quantity'],
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
