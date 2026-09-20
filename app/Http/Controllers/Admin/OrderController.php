<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin Inertia shells for admin order management (spec §17). Detail
 * reuses the same order + items + payment scope as AdminOrderController.
 */
class OrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Orders/Index');
    }

    public function show(string $orderNumber): Response
    {
        $order = Order::query()->where('order_number', $orderNumber)->with(['items.seller', 'payment', 'sellerTrackings'])->firstOrFail();

        $order->markExpiredIfOverdue();

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'currency_code' => $order->currency_code,
                'customer_name' => $order->customer_name,
                'customer_address' => $order->customer_address,
                'customer_state' => $order->customer_state,
                'customer_city' => $order->customer_city,
                'customer_post_code' => $order->customer_post_code,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'subtotal' => $order->subtotal,
                'shipping_fee' => $order->shipping_fee,
                'total' => $order->total,
                'shipping_method' => $order->shipping_method,
                'shipping_address' => $order->shipping_address,
                'shipping_state' => $order->shipping_state,
                'shipping_city' => $order->shipping_city,
                'shipping_post_code' => $order->shipping_post_code,
                'notes' => $order->notes,
                'expired_at' => $order->expired_at,
                'created_at' => $order->created_at,
                'payment' => $order->payment ? [
                    'id' => $order->payment->id,
                    'payment_method' => $order->payment->payment_method instanceof \BackedEnum ? $order->payment->payment_method->value : $order->payment->payment_method,
                    'payment_gateway' => $order->payment->payment_gateway,
                    'status' => $order->payment->status instanceof \BackedEnum ? $order->payment->status->value : $order->payment->status,
                    'amount' => $order->payment->amount,
                    'transaction_id' => $order->payment->transaction_id,
                    'proof_url' => $order->payment->proofUrl(),
                    'proof_uploaded_at' => $order->payment->proof_uploaded_at,
                    'paid_at' => $order->payment->paid_at,
                ] : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name_snapshot,
                    'product_slug' => $item->product_slug_snapshot,
                    'price' => $item->price_snapshot,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                    'seller_id' => $item->seller_id,
                    'seller' => $item->seller?->store_name,
                ])->values(),
                'seller_trackings' => $order->sellerTrackings->loadMissing('seller')->map(fn ($tracking) => [
                    'seller_id' => $tracking->seller_id,
                    'seller_name' => $tracking->seller?->store_name,
                    'courier' => $tracking->courier,
                    'waybill_number' => $tracking->waybill_number,
                    'tracking_url' => $tracking->tracking_url,
                    'tracking_status' => $tracking->tracking_status,
                    'received_at' => $tracking->received_at,
                    'packed_at' => $tracking->packed_at,
                    'picked_up_at' => $tracking->picked_up_at,
                    'delivered_at' => $tracking->delivered_at,
                    'delivery_photo_url' => $tracking->delivery_photo_path ? Storage::disk('public')->url($tracking->delivery_photo_path) : null,
                ])->values(),
            ],
        ]);
    }
}
