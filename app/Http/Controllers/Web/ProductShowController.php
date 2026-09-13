<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public product detail page (spec §18.5). Buy Now initiates direct
 * checkout without a cart backend; cart affordances stay UI placeholders.
 */
class ProductShowController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with(['seller', 'category', 'images'])
            ->firstOrFail();

        $sellerProducts = Product::query()
            ->active()
            ->where('seller_id', $product->seller_id)
            ->whereKeyNot($product->getKey())
            ->with(['seller', 'category', 'images'])
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('Product/Show', [
            'product' => new ProductResource($product),
            'sellerProducts' => ProductResource::collection($sellerProducts),
        ]);
    }
}
