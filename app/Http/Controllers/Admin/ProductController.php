<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\CurrencyFormatter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin Inertia shells for admin product moderation (spec §17). Data
 * flows through the admin JSON API; the controller only gates/renders.
 */
class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Products/Index', [
            'sellers' => Seller::query()->orderBy('store_name')->get(['id', 'store_name']),
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Product $product, CurrencyFormatter $currency): Response
    {
        $product->load(['seller', 'category', 'images', 'flashSales'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        $paidStatuses = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];
        $paidItems = $product->orderItems()->whereHas('order', fn ($query) => $query->whereIn('status', $paidStatuses));
        $flashSale = $product->activeFlashSale();

        return Inertia::render('Admin/Products/Show', [
            'product' => $product,
            'public_url' => $product->status === ProductStatus::Active ? route('products.show', $product->slug) : null,
            'stats' => [
                'sold' => (int) (clone $paidItems)->sum('quantity'),
                'revenue_formatted' => $currency->format((float) (clone $paidItems)->sum('subtotal')),
                'orders' => (int) (clone $paidItems)->distinct()->count('order_id'),
                'rating' => $product->reviews_avg_rating !== null ? round((float) $product->reviews_avg_rating, 1) : null,
                'reviews' => (int) $product->reviews_count,
            ],
            'flash_sale' => $flashSale === null ? null : [
                'price_formatted' => $currency->format((float) $flashSale->price),
                'ends_at' => $flashSale->ends_at?->toIso8601String(),
            ],
            'price_formatted' => $currency->format((float) $product->price),
            'recent_orders' => $product->orderItems()
                ->with('order:id,order_number,customer_name,status,created_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->filter(fn ($item) => $item->order !== null)
                ->map(fn ($item): array => [
                    'order_number' => $item->order->order_number,
                    'customer_name' => $item->order->customer_name,
                    'status' => $item->order->status instanceof \BackedEnum ? $item->order->status->value : $item->order->status,
                    'quantity' => $item->quantity,
                    'created_at' => $item->order->created_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product->load(['seller', 'category', 'images']),
        ]);
    }
}
