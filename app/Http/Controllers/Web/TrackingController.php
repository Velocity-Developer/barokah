<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $order = null;
        $error = null;
        $orderNumber = trim((string) $request->query('order_number', ''));

        if ($orderNumber !== '') {
            $order = Order::query()
                ->where('order_number', $orderNumber)
                ->with(['sellerTrackings', 'items.product', 'items.review'])
                ->first();

            if ($order === null || $order->isExpired()) {
                $error = 'Order number not found. Please check and try again.';
                $order = null;
            }
        }

        return Inertia::render('Tracking/Index', [
            'orderNumber' => $orderNumber,
            'error' => $error,
            'order' => $order ? [
                'order_number' => $order->order_number,
                'can_review' => $request->user()?->id !== null && $request->user()->id === $order->user_id,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'tracking_status' => $order->sellerTrackings->first()?->tracking_status,
                'courier' => $order->sellerTrackings->first()?->courier,
                'waybill_number' => $order->sellerTrackings->first()?->waybill_number,
                'tracking_url' => $order->sellerTrackings->first()?->tracking_url,
                'seller_trackings' => $order->sellerTrackings->map(fn ($tracking): array => [
                    'courier' => $tracking->courier,
                    'waybill_number' => $tracking->waybill_number,
                    'tracking_status' => $tracking->tracking_status,
                    'items' => $order->items->where('seller_id', $tracking->seller_id)->map(fn ($item): array => [
                        'id' => $item->id,
                        'product_name' => $item->product_name_snapshot,
                        'product_slug' => $item->product_slug_snapshot,
                        'reviewed' => $item->review !== null,
                        'rating' => $item->review?->rating,
                        'review' => $item->review?->review,
                    ])->values(),
                    'received_at' => $tracking->received_at?->format('d/m/Y H:i'),
                    'packed_at' => $tracking->packed_at?->format('d/m/Y H:i'),
                    'picked_up_at' => $tracking->picked_up_at?->format('d/m/Y H:i'),
                    'delivered_at' => $tracking->delivered_at?->format('d/m/Y H:i'),
                    'tracking_url' => $tracking->tracking_url,
                    'delivery_photo_url' => $tracking->delivery_photo_path ? Storage::disk('public')->url($tracking->delivery_photo_path) : null,
                ])->values(),
            ] : null,
        ]);
    }
}
