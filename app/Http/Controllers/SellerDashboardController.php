<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerDashboardController extends Controller
{
    /**
     * Seller dashboard with seller-scoped order aggregates.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user()->load('seller');
        $sellerId = $user->seller?->id;

        $items = $sellerId === null
            ? OrderItem::query()->whereRaw('1 = 0')
            : OrderItem::query()->where('seller_id', $sellerId);

        // TBC (spec §13/§14.3, tasks SubTask 3.1): dashboard shows order
        // counts and item revenue only. Product metrics land in Task 4 once
        // the products table exists.
        $flashSales = $sellerId === null
            ? collect()
            : FlashSale::query()
                ->whereHas('product', fn ($query) => $query->where('seller_id', $sellerId))
                ->with('product:id,name,slug')
                ->latest('starts_at')
                ->get();

        return Inertia::render('Seller/Dashboard', [
            'seller' => $user->seller,
            'flashSales' => $flashSales->map(fn (FlashSale $sale): array => [
                'id' => $sale->id,
                'product_name' => $sale->product->name,
                'product_slug' => $sale->product->slug,
                'price' => $sale->price,
                'quantity' => $sale->quantity,
                'quantity_sold' => $sale->quantity_sold,
                'remaining_quantity' => $sale->remainingQuantity(),
                'starts_at' => $sale->starts_at,
                'ends_at' => $sale->ends_at,
                'status' => $sale->isActive() ? 'active' : ($sale->starts_at->isFuture() ? 'scheduled' : ($sale->remainingQuantity() === 0 ? 'sold_out' : 'ended')),
            ]),
            'stats' => [
                'orders_count' => (clone $items)->distinct()->count('order_id'),
                'items_count' => (clone $items)->count(),
                'revenue' => (string) (clone $items)->sum('subtotal'),
            ],
        ]);
    }
}
