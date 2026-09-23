<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\OrderSellerTracking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Order view scoped to a single seller: only that seller's items are
 * included. Order-level totals still reflect the whole checkout
 * (spec §14.3); seller revenue is derived from the included items.
 *
 * @mixin Order
 */
class SellerOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'tracking_status' => $this->sellerTrackings->first()?->tracking_status,
            'payment' => $this->payment ? [
                'status' => $this->payment->status instanceof \BackedEnum ? $this->payment->status->value : $this->payment->status,
                'payment_method' => $this->payment->payment_method instanceof \BackedEnum ? $this->payment->payment_method->value : $this->payment->payment_method,
            ] : null,
            'currency_code' => $this->currency_code,
            'customer_name' => $this->customer_name,
            'customer_address' => $this->customer_address,
            'customer_state' => $this->customer_state,
            'customer_city' => $this->customer_city,
            'customer_post_code' => $this->customer_post_code,
            'shipping_address' => $this->shipping_address,
            'shipping_state' => $this->shipping_state,
            'shipping_city' => $this->shipping_city,
            'shipping_post_code' => $this->shipping_post_code,
            'shipping_method' => $this->shipping_method,
            'subtotal' => $this->items->sum('subtotal'),
            'shipping_fee' => $this->sellerTrackings->first()?->shipping_fee ?? 0,
            'total' => (float) $this->items->sum('subtotal') + (float) ($this->sellerTrackings->first()?->shipping_fee ?? 0),
            'tracking' => $this->sellerTrackings->first()
                ? $this->serializeTracking($this->sellerTrackings->first())
                : null,
            'created_at' => $this->created_at,
            // Handy for the list, which shows one line plus a "+N more" hint.
            'items_count' => $this->whenLoaded('items', fn (): int => $this->items->count()),
            'first_item' => $this->whenLoaded('items', fn (): ?string => $this->items->first()?->product_name_snapshot),
            'items' => SellerOrderItemResource::collection($this->whenLoaded('items')),
        ];
    }

    /**
     * Serialize a seller tracking with absolute URLs and typed fields.
     *
     * @return array<string, mixed>
     */
    private function serializeTracking(OrderSellerTracking $tracking): array
    {
        return [
            'courier' => $tracking->courier,
            'waybill_number' => $tracking->waybill_number,
            'tracking_url' => $tracking->tracking_url,
            'tracking_status' => $tracking->tracking_status,
            'received_at' => $tracking->received_at,
            'packed_at' => $tracking->packed_at,
            'picked_up_at' => $tracking->picked_up_at,
            'delivered_at' => $tracking->delivered_at,
            'delivery_photo_path' => $tracking->delivery_photo_path,
            'delivery_photo_url' => $tracking->delivery_photo_path
                ? Storage::disk('public')->url($tracking->delivery_photo_path)
                : null,
        ];
    }
}
