<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Services\CurrencyFormatter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin Inertia shells for admin store management (spec §17). Data flows
 * through the admin JSON API; the controller only gates and renders.
 */
class SellerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Sellers/Index');
    }

    public function show(Seller $seller, CurrencyFormatter $currency): Response
    {
        $paidStatuses = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];
        $paidItems = OrderItem::query()
            ->where('seller_id', $seller->id)
            ->whereHas('order', fn ($query) => $query->whereIn('status', $paidStatuses));

        $recentOrders = Order::query()
            ->whereHas('items', fn ($query) => $query->where('seller_id', $seller->id))
            ->with(['items' => fn ($query) => $query->where('seller_id', $seller->id)])
            ->latest()
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                'items' => $order->items->sum('quantity'),
                'store_total_formatted' => $currency->format((float) $order->items->sum('subtotal')),
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        $reviews = $seller->productReviews();

        return Inertia::render('Admin/Sellers/Show', [
            'seller' => $this->sellerPayload($seller),
            'public_url' => $seller->status === SellerStatus::Active ? route('sellers.show', $seller->slug) : null,
            'stats' => [
                'revenue_formatted' => $currency->format((float) (clone $paidItems)->sum('subtotal')),
                'units_sold' => (int) (clone $paidItems)->sum('quantity'),
                'orders' => (int) (clone $paidItems)->distinct()->count('order_id'),
                'followers' => $seller->followers()->count(),
                'rating' => ($average = (clone $reviews)->avg('rating')) !== null ? round((float) $average, 1) : null,
                'reviews' => (clone $reviews)->count(),
            ],
            'recent_orders' => $recentOrders,
        ]);
    }

    public function edit(Seller $seller): Response
    {
        return Inertia::render('Admin/Sellers/Edit', [
            'seller' => $this->sellerPayload($seller),
        ]);
    }

    /**
     * Explicit seller payload with unwrapped enums and timestamps
     * (same pattern as OrderController detail mapping).
     *
     * @return array<string, mixed>
     */
    protected function sellerPayload(Seller $seller): array
    {
        $seller->load(['user', 'products' => fn ($query) => $query->with('images')->latest()]);

        return [
            'id' => $seller->id,
            'store_name' => $seller->store_name,
            'slug' => $seller->slug,
            'status' => $seller->status instanceof \BackedEnum ? $seller->status->value : $seller->status,
            'description' => $seller->description,
            // Own media (what the edit form manages) and what shoppers see.
            'profile_photo_url' => $seller->profile_photo_url,
            'banner_url' => $seller->banner_url,
            'display_photo_url' => $seller->displayPhotoUrl(),
            'display_banner_url' => $seller->displayBannerUrl(),
            'phone' => $seller->phone,
            'whatsapp' => $seller->whatsapp,
            'store_location' => $seller->store_location,
            'bank_account' => $seller->bank_account,
            'state' => $seller->state,
            'city' => $seller->city,
            'created_at' => $seller->created_at,
            'updated_at' => $seller->updated_at,
            'owner' => $seller->user ? [
                'id' => $seller->user->id,
                'name' => $seller->user->name,
                'email' => $seller->user->email,
                'phone' => $seller->user->phone,
                'is_active_as_seller' => (bool) $seller->user->is_active_as_seller,
            ] : null,
            'products' => $seller->products->map(fn ($product) => [
                'image' => $product->images->firstWhere('is_primary', true)?->url ?? $product->images->first()?->url,
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'stock' => $product->stock,
                'status' => $product->status instanceof \BackedEnum ? $product->status->value : $product->status,
            ])->values(),
        ];
    }
}
