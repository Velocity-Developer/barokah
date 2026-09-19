<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function store(Request $request, OrderItem $orderItem): ProductReviewResource
    {
        $user = $request->user();
        $orderItem->load(['order', 'product']);

        if ($orderItem->order->user_id !== $user->id || $orderItem->product === null) {
            abort(404);
        }

        if (! $orderItem->order->sellerTrackings()->where('seller_id', $orderItem->seller_id)->whereNotNull('delivered_at')->exists()) {
            throw ValidationException::withMessages(['order_item' => 'Product can be rated after delivery.']);
        }

        if ($orderItem->review()->exists()) {
            throw ValidationException::withMessages(['order_item' => 'This product has already been rated.']);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:20480'],
        ]);

        $media = collect($request->file('images', []))
            ->map(fn ($file): array => [
                'type' => 'image',
                'url' => Storage::disk('public')->url($file->store('product-reviews', 'public')),
            ])
            ->values()
            ->all();

        if ($request->hasFile('video')) {
            $media[] = [
                'type' => 'video',
                'url' => Storage::disk('public')->url($request->file('video')->store('product-reviews', 'public')),
            ];
        }

        return new ProductReviewResource($orderItem->review()->create([
            'product_id' => $orderItem->product_id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'review' => $validated['review'] ?? null,
            'media' => $media,
        ]));
    }
}
