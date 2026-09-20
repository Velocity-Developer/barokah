<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SellerOrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class SellerOrderController extends Controller
{
    /**
     * List orders containing items owned by the current seller.
     *
     * Scoped via order_items.seller_id (spec §14.3); all order statuses
     * including pending_payment are visible so the seller can see unpaid
     * reservations containing their products as well. Only the current
     * seller's items are serialized.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $sellerId = $request->user()->seller()->value('id');

        $orders = Order::query()
            ->whereHas('items', fn ($query) => $query->where('seller_id', $sellerId))
            ->with(['items' => fn ($query) => $query->where('seller_id', $sellerId)])
            ->latest()
            ->paginate(15);

        return SellerOrderResource::collection($orders);
    }

    public function update(Request $request, string $orderNumber): SellerOrderResource
    {
        $sellerId = $request->user()->seller()->value('id');
        $validated = $request->validate([
            'courier' => ['nullable', 'string', 'max:255'],
            'waybill_number' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'tracking_status' => ['required', 'in:received,packed,shipped,picked_up,delivered'],
            'delivery_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->whereHas('items', fn ($query) => $query->where('seller_id', $sellerId))
            ->firstOrFail();

        // Only orders that have been paid can be processed by the seller.
        if ($order->payment?->status !== PaymentStatus::Paid) {
            abort(403, 'Order is not paid yet.');
        }

        $deliveryPhoto = $request->file('delivery_photo');
        unset($validated['delivery_photo']);

        $timestamp = now();
        $timestampField = $validated['tracking_status'] === 'shipped'
            ? 'picked_up_at'
            : $validated['tracking_status'].'_at';
        $validated[$timestampField] = $timestamp;
        $validated['tracking_status'] = $validated['tracking_status'] === 'shipped'
            ? 'picked_up'
            : $validated['tracking_status'];

        $tracking = $order->sellerTrackings()->updateOrCreate(
            ['seller_id' => $sellerId],
            $validated,
        );

        if ($deliveryPhoto !== null && $validated['tracking_status'] === 'delivered') {
            if ($tracking->delivery_photo_path !== null) {
                Storage::disk('public')->delete($tracking->delivery_photo_path);
            }

            $tracking->update(['delivery_photo_path' => $deliveryPhoto->store('delivery-proofs', 'public')]);
        }

        return new SellerOrderResource($order->refresh()->load([
            'items' => fn ($query) => $query->where('seller_id', $sellerId),
            'sellerTrackings' => fn ($query) => $query->whereKey($tracking->id),
        ]));
    }

    /**
     * Show one order scoped to the current seller's items.
     *
     * Orders without the seller's items return 404 so sellers cannot
     * probe other sellers' orders. All order statuses (including
     * pending_payment) are reachable; the front-end hides editing UI
     * for unpaid orders and the update action rejects them with 403.
     */
    public function show(Request $request, string $orderNumber): SellerOrderResource
    {
        $sellerId = $request->user()->seller()->value('id');

        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->whereHas('items', fn ($query) => $query->where('seller_id', $sellerId))
            ->with(['items' => fn ($query) => $query->where('seller_id', $sellerId), 'payment', 'sellerTrackings' => fn ($query) => $query->where('seller_id', $sellerId)])
            ->first();

        if ($order === null || $request->user()?->cannot('view', $order)) {
            abort(404);
        }

        return new SellerOrderResource($order);
    }
}
