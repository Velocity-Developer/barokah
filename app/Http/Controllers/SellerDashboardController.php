<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seller dashboard: the store's own sales, the orders waiting on it and the
 * stock that needs attention. Everything is scoped through
 * order_items.seller_id (spec §14.3), so other sellers' lines in a shared
 * checkout stay hidden.
 */
class SellerDashboardController extends Controller
{
    private const LOW_STOCK = 5;

    private const CHART_DAYS = 14;

    private const RECENT_ORDERS = 8;

    /** Orders that count as money earned. */
    private const PAID_STATUSES = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    public function __invoke(Request $request, CurrencyFormatter $currency, SettingsService $settings): Response
    {
        $seller = $request->user()->load('seller')->seller;
        $sellerId = $seller?->id;

        $timezone = (string) $settings->get('localization.timezone', config('app.timezone'));
        $now = Carbon::now($timezone);

        $items = fn (): Builder => OrderItem::query()
            ->when($sellerId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('seller_id', $sellerId);

        $paidItems = fn (): Builder => $items()->whereHas('order', fn (Builder $order) => $order->whereIn('status', self::PAID_STATUSES));

        $orders = fn (): Builder => Order::query()
            ->when($sellerId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereHas('items', fn (Builder $item) => $item->where('seller_id', $sellerId));

        $products = fn (): Builder => Product::query()
            ->when($sellerId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('seller_id', $sellerId);

        $revenue = (float) $paidItems()->sum('subtotal');
        $revenueThisMonth = (float) $paidItems()
            ->whereHas('order', fn (Builder $order) => $order->where('created_at', '>=', $now->copy()->startOfMonth()->utc()))
            ->sum('subtotal');

        $lowStock = $products()
            ->where('status', ProductStatus::Active)
            ->where('stock', '<=', self::LOW_STOCK)
            ->orderBy('stock')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'stock']);

        $chart = $this->revenueChart($sellerId, $now, $currency);

        return Inertia::render('Seller/Dashboard', [
            'seller' => [
                'id' => $seller?->id,
                'store_name' => $seller?->store_name,
                'slug' => $seller?->slug,
                'status' => $seller?->status instanceof \BackedEnum ? $seller->status->value : $seller?->status,
                'profile_photo_url' => $seller?->displayPhotoUrl(),
                'banner_url' => $seller?->displayBannerUrl(),
                'public_url' => $seller !== null && $seller->status === SellerStatus::Active
                    ? route('sellers.show', $seller->slug)
                    : null,
            ],
            'stats' => [
                'currency_code' => $currency->code(),
                'revenue_formatted' => $currency->format($revenue),
                'revenue_this_month_formatted' => $currency->format($revenueThisMonth),
                'orders_count' => $orders()->count(),
                'orders_today' => $orders()->where('created_at', '>=', $now->copy()->startOfDay()->utc())->count(),
                'items_sold' => (int) $paidItems()->sum('quantity'),
                'awaiting_payment' => $orders()->where('status', OrderStatus::PendingPayment)->count(),
                'product_count' => $products()->count(),
                'active_product_count' => $products()->where('status', ProductStatus::Active)->count(),
                'low_stock_count' => $products()->where('stock', '<=', self::LOW_STOCK)->count(),
                'followers_count' => $sellerId === null ? 0 : $seller->followers()->count(),
                'average_rating' => $sellerId === null ? null : round((float) $seller->productReviews()->avg('rating'), 1),
                'ratings_count' => $sellerId === null ? 0 : $seller->productReviews()->count(),
            ],
            'attention' => [
                'orders_to_ship' => $orders()->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing])->count(),
                'awaiting_payment' => $orders()->where('status', OrderStatus::PendingPayment)->count(),
                'inactive_products' => $products()->where('status', '!=', ProductStatus::Active)->count(),
                'low_stock_products' => $lowStock->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $product->stock,
                ])->values(),
            ],
            'revenue_chart' => $chart,
            'revenue_chart_total_formatted' => $currency->format(array_sum(array_column($chart, 'total'))),
            'recent_orders' => $orders()
                // with() hands the closure a relation, not a query builder.
                ->with(['items' => fn ($relation) => $relation->where('seller_id', $sellerId)])
                ->latest()
                ->orderByDesc('id')
                ->limit(self::RECENT_ORDERS)
                ->get()
                ->map(fn (Order $order): array => [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'first_item' => $order->items->first()?->product_name_snapshot,
                    'items_count' => $order->items->count(),
                    'store_total_formatted' => $currency->format((float) $order->items->sum('subtotal')),
                    'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                    'created_at' => $order->created_at?->toIso8601String(),
                ]),
            'flash_sales' => $this->flashSales($sellerId, $currency),
        ]);
    }

    /**
     * Running and upcoming flash sales only; the finished ones live on the
     * flash sales page.
     *
     * @return list<array<string, mixed>>
     */
    private function flashSales(?int $sellerId, CurrencyFormatter $currency): array
    {
        if ($sellerId === null) {
            return [];
        }

        return FlashSale::query()
            ->whereHas('product', fn (Builder $query) => $query->where('seller_id', $sellerId))
            ->where('ends_at', '>=', now())
            ->with('product:id,name,slug')
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(fn (FlashSale $sale): array => [
                'id' => $sale->id,
                'product_name' => $sale->product->name,
                'price_formatted' => $currency->format((float) $sale->price),
                'quantity' => $sale->quantity,
                'quantity_sold' => $sale->quantity_sold,
                'remaining_quantity' => $sale->remainingQuantity(),
                'starts_at' => $sale->starts_at?->toIso8601String(),
                'ends_at' => $sale->ends_at?->toIso8601String(),
                'status' => $sale->isActive() ? 'active' : 'scheduled',
            ])
            ->values()
            ->all();
    }

    /**
     * This store's paid revenue per day for the last CHART_DAYS days, in the
     * store's time zone.
     *
     * @return list<array{date: string, label: string, total: float, total_formatted: string, orders: int}>
     */
    private function revenueChart(?int $sellerId, Carbon $now, CurrencyFormatter $currency): array
    {
        $start = $now->copy()->startOfDay()->subDays(self::CHART_DAYS - 1);

        // Grouped in PHP so the store time zone applies the same way on MySQL and SQLite.
        $byDay = $sellerId === null
            ? collect()
            : OrderItem::query()
                ->where('seller_id', $sellerId)
                ->whereHas('order', fn (Builder $order) => $order
                    ->whereIn('status', self::PAID_STATUSES)
                    ->where('created_at', '>=', $start->copy()->utc()))
                ->with('order:id,created_at')
                ->get(['id', 'order_id', 'subtotal'])
                ->groupBy(fn (OrderItem $item): string => $item->order->created_at->copy()->setTimezone($now->getTimezone())->toDateString());

        $days = [];

        for ($day = $start->copy(); $day->lte($now); $day->addDay()) {
            $dayItems = $byDay->get($day->toDateString(), collect());
            $total = round((float) $dayItems->sum('subtotal'), 2);

            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('j M'),
                'total' => $total,
                'total_formatted' => $currency->format($total),
                'orders' => $dayItems->pluck('order_id')->unique()->count(),
            ];
        }

        return $days;
    }
}
