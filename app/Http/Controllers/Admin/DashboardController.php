<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Seller;
use App\Services\CurrencyFormatter;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin dashboard with marketplace metrics (spec §17): KPIs, a 14-day
 * revenue chart, things that need attention and the latest orders.
 */
class DashboardController extends Controller
{
    private const LOW_STOCK = 5;

    private const CHART_DAYS = 14;

    public function __invoke(CurrencyFormatter $currency, SettingsService $settings): Response
    {
        $paidStatuses = [
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Completed,
        ];

        $timezone = (string) $settings->get('localization.timezone', config('app.timezone'));
        $now = Carbon::now($timezone);

        $revenue = (float) Order::query()->whereIn('status', $paidStatuses)->sum('total');
        $revenueThisMonth = (float) Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', $now->copy()->startOfMonth()->utc())
            ->sum('total');

        $lowStock = Product::query()
            ->where('status', ProductStatus::Active)
            ->where('stock', '<=', self::LOW_STOCK)
            ->with('seller:id,store_name')
            ->orderBy('stock')
            ->limit(5)
            ->get(['id', 'name', 'stock', 'seller_id']);

        $chart = $this->revenueChart($paidStatuses, $now, $currency);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'orders_count' => Order::query()->count(),
                'orders_today' => Order::query()->where('created_at', '>=', $now->copy()->startOfDay()->utc())->count(),
                'revenue' => (string) $revenue,
                'revenue_formatted' => $currency->format($revenue),
                'revenue_this_month_formatted' => $currency->format($revenueThisMonth),
                'currency_code' => $currency->code(),
                'pending_payments' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
                'pending_orders' => Order::query()->pendingPayment()->count(),
                'low_stock_count' => Product::query()->where('stock', '<=', self::LOW_STOCK)->count(),
                'seller_count' => Seller::query()->count(),
                'active_seller_count' => Seller::query()->where('status', SellerStatus::Active)->count(),
                'product_count' => Product::query()->count(),
                'active_product_count' => Product::query()->where('status', ProductStatus::Active)->count(),
            ],
            'attention' => [
                'seller_applications' => Seller::query()->where('status', SellerStatus::Pending)->count(),
                'manual_payments' => Payment::query()->where('status', PaymentStatus::Pending)->where('payment_gateway', 'manual')->count(),
                'orders_to_ship' => Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Processing])->count(),
                'low_stock_products' => $lowStock->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $product->stock,
                    'store' => $product->seller?->store_name,
                ])->values(),
            ],
            'revenue_chart' => $chart,
            'revenue_chart_total_formatted' => $currency->format(array_sum(array_column($chart, 'total'))),
            'recent_orders' => Order::query()
                ->withCount('items')
                ->with('items:id,order_id,product_name_snapshot')
                ->latest()
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'first_item' => $order->items->first()?->product_name_snapshot,
                    'items_count' => $order->items_count,
                    'total_formatted' => $currency->format((float) $order->total),
                    'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                    'created_at' => $order->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Paid revenue per day for the last CHART_DAYS days, in the store's time zone.
     *
     * @param  list<OrderStatus>  $paidStatuses
     * @return list<array{date: string, label: string, total: float, total_formatted: string, orders: int}>
     */
    private function revenueChart(array $paidStatuses, Carbon $now, CurrencyFormatter $currency): array
    {
        $start = $now->copy()->startOfDay()->subDays(self::CHART_DAYS - 1);

        // Grouped in PHP so the store time zone applies the same way on MySQL and SQLite.
        $byDay = Order::query()
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', $start->copy()->utc())
            ->get(['created_at', 'total'])
            ->groupBy(fn (Order $order): string => $order->created_at->copy()->setTimezone($now->getTimezone())->toDateString());

        $days = [];

        for ($day = $start->copy(); $day->lte($now); $day->addDay()) {
            $orders = $byDay->get($day->toDateString(), collect());
            $total = round((float) $orders->sum('total'), 2);

            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('j M'),
                'total' => $total,
                'total_formatted' => $currency->format($total),
                'orders' => $orders->count(),
            ];
        }

        return $days;
    }
}
