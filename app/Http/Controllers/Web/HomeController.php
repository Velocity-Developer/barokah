<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\SellerResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
    private const RECOMMENDATIONS_PER_PAGE = 24;

    public function __invoke(Request $request): Response
    {
        // Closures keep the "Load more" partial reload from re-running these queries.
        $categories = fn () => Category::query()
            ->active()
            ->withCount(['products' => fn (Builder $query) => $query->where('status', ProductStatus::Active)])
            // The most reviewed active product in each category supplies the card image.
            ->with(['products' => fn ($query) => $query
                ->active()
                ->with('images')
                ->withCount('reviews')
                ->orderByDesc('reviews_count')
                ->latest()
                ->limit(1)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(20)
            ->get();

        // Featured: most followed first, then best rated; stores without active
        // products still show, but after the others.
        $sellers = fn () => Seller::query()
            ->where('status', SellerStatus::Active)
            ->with('user')
            ->withCount([
                'products' => fn (Builder $query) => $query->where('status', ProductStatus::Active),
                'followers',
                'productReviews as ratings_count' => fn (Builder $query) => $query->where('products.status', ProductStatus::Active),
            ])
            ->withAvg(
                ['productReviews as average_rating' => fn (Builder $query) => $query->where('products.status', ProductStatus::Active)],
                'rating',
            )
            ->orderByRaw('CASE WHEN products_count > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('followers_count')
            ->orderByDesc('average_rating')
            ->orderByDesc('products_count')
            ->latest()
            ->limit(8)
            ->get();

        $recommendations = Product::query()
            ->active()
            ->with(['seller', 'category', 'images'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::RECOMMENDATIONS_PER_PAGE, pageName: 'recommendations_page');

        $flashSaleProducts = fn () => Product::query()
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

        $bestSellers = fn () => Product::query()
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
            'categories' => fn () => CategoryResource::collection($categories()),
            'sellers' => fn () => SellerResource::collection($sellers()),
            // Each "Load more" request appends the next page to this list.
            'latestProducts' => Inertia::merge(
                fn () => ProductResource::collection($recommendations->items())->resolve($request),
            ),
            'latestProductsHasMore' => $recommendations->hasMorePages(),
            'flashSaleProducts' => fn () => ProductResource::collection($flashSaleProducts()),
            'bestSellerProducts' => fn () => ProductResource::collection($bestSellers()),
        ]);
    }
}
