<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\SellerResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marketplace homepage (spec §18.3/§18.7).
 *
 * Homepage sections use active products. Flash-sale section only uses products
 * with an active flash sale.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $categories = Category::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(20)
            ->get();

        $sellers = Seller::query()
            ->where('status', SellerStatus::Active)
            ->latest()
            ->limit(12)
            ->get();

        $latest = Product::query()
            ->active()
            ->with(['seller', 'category', 'images'])
            ->latest()
            ->limit(24)
            ->get();

        $flashSaleProducts = Product::query()
            ->active()
            ->whereHas('flashSales', fn ($query) => $query->active())
            ->with(['seller', 'category', 'images', 'flashSales'])
            ->latest()
            ->limit(8)
            ->get();

        $soldStatuses = [
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Completed,
        ];

        $bestSellers = Product::query()
            ->active()
            ->with(['seller', 'category', 'images'])
            ->whereHas('orderItems.order', fn (Builder $order) => $order->whereIn('status', $soldStatuses))
            ->withSum(
                ['orderItems as sold_count' => fn (Builder $items) => $items
                    ->whereHas('order', fn (Builder $order) => $order->whereIn('status', $soldStatuses))],
                'quantity'
            )
            ->orderByDesc('sold_count')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return Inertia::render('Home', [
            'categories' => CategoryResource::collection($categories),
            'sellers' => SellerResource::collection($sellers),
            'latestProducts' => ProductResource::collection($latest),
            'flashSaleProducts' => ProductResource::collection($flashSaleProducts),
            'bestSellerProducts' => ProductResource::collection($bestSellers),
        ]);
    }
}
