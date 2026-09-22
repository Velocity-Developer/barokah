<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public product detail page (spec §18.5). Buy Now initiates direct
 * checkout without a cart backend; cart affordances stay UI placeholders.
 */
class ProductShowController extends Controller
{
    public function __invoke(Request $request, string $slug): Response
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with(['seller', 'category', 'images', 'latestReviews.user'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->firstOrFail();

        $product->setAttribute('is_favorited', $request->user()?->hasFavorited($product) ?? false);

        $sellerProducts = Product::query()
            ->active()
            ->where('seller_id', $product->seller_id)
            ->whereKeyNot($product->getKey())
            ->with(['seller', 'category', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('Product/Show', [
            'product' => new ProductResource($product),
            'sellerProducts' => ProductResource::collection($sellerProducts),
        ]);
    }
}
