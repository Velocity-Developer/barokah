<?php

namespace App\Http\Controllers\Web;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Http\Resources\Api\V1\SellerResource;
use App\Models\ProductReview;
use App\Models\Seller;
use Inertia\Inertia;
use Inertia\Response;

class SellerShowController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $seller = Seller::query()
            ->where('status', SellerStatus::Active)
            ->where('slug', $slug)
            ->with(['products' => fn ($query) => $query
                ->active()
                ->with(['images', 'category'])
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')])
            ->firstOrFail();

        $reviews = ProductReview::query()
            ->whereHas('product', fn ($query) => $query
                ->where('seller_id', $seller->id)
                ->active())
            ->with(['user', 'product' => fn ($query) => $query
                ->with('images')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')])
            ->latest()
            ->get();

        $seller->setAttribute('average_rating', $reviews->avg('rating'));
        $seller->setAttribute('ratings_count', $reviews->count());

        return Inertia::render('Store/Show', [
            'seller' => new SellerResource($seller),
            'products' => ProductResource::collection($seller->products),
            'reviews' => ProductReviewResource::collection($reviews),
        ]);
    }
}
