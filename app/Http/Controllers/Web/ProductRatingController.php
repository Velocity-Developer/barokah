<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductRatingController extends Controller
{
    public function __invoke(Request $request, OrderItem $orderItem): Response
    {
        $orderItem->load(['order', 'product', 'review']);

        abort_unless($orderItem->order->user_id === $request->user()->id, 404);
        abort_unless($orderItem->product !== null, 404);
        abort_unless($orderItem->order->sellerTrackings()->where('seller_id', $orderItem->seller_id)->whereNotNull('delivered_at')->exists(), 404);

        return Inertia::render('Rating/Show', [
            'orderItem' => [
                'id' => $orderItem->id,
                'product_name' => $orderItem->product_name_snapshot,
                'product_slug' => $orderItem->product_slug_snapshot,
                'reviewed' => $orderItem->review !== null,
                'rating' => $orderItem->review?->rating,
                'review' => $orderItem->review?->review,
            ],
        ]);
    }
}
