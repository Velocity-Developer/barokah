<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Admin order management (spec §11.4/§17): list/filter all orders and
 * view any order detail with items. Status mutation lands with
 * PaymentService in Task 8; Task 7 is read-only for admins.
 */
class AdminOrderController extends Controller
{
    /** @var array<string, array{0: string, 1: string}> */
    private const SORTS = [
        'latest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'total_desc' => ['total', 'desc'],
        'total_asc' => ['total', 'asc'],
    ];

    /**
     * Paginated admin order list with search, filters and sorting.
     */
    public function index(Request $request, SettingsService $settings): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'seller_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ]);

        $timezone = (string) $settings->get('localization.timezone', config('app.timezone'));
        $filtered = $this->filteredQuery($validated, $timezone);

        // Counts per status for the filter tabs, respecting every filter except the status itself.
        $statusCounts = (clone $filtered)
            ->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        [$column, $direction] = self::SORTS[$validated['sort'] ?? 'latest'];

        $orders = $filtered
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['items', 'payment'])
            ->withCount('items')
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return OrderResource::collection($orders)->additional([
            'status_counts' => $statusCounts,
            'status_counts_total' => $statusCounts->sum(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    private function filteredQuery(array $filters, string $timezone): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Order::query()
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $inner->where('order_number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhere('customer_email', 'like', $like)
                    ->orWhere('coupon_code', 'like', $like)
                    ->orWhereHas('items', fn (Builder $items) => $items
                        ->where('product_name_snapshot', 'like', $like)
                        ->orWhereHas('seller', fn (Builder $seller) => $seller->where('store_name', 'like', $like)));
            }))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $method) => $query->whereHas('payment', fn (Builder $payment) => $payment->where('payment_method', $method)))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->whereHas('payment', fn (Builder $payment) => $payment->where('status', $status)))
            ->when($filters['seller_id'] ?? null, fn (Builder $query, int $sellerId) => $query->whereHas('items', fn (Builder $items) => $items->where('seller_id', $sellerId)))
            // Dates are picked in the store time zone; stored timestamps are UTC.
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', Carbon::parse($date, $timezone)->startOfDay()->utc()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', Carbon::parse($date, $timezone)->endOfDay()->utc()));
    }

    /**
     * Admin detail view for any order by order_number.
     */
    public function show(string $orderNumber): OrderResource
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->with(['items', 'payment', 'sellerTrackings'])
            ->firstOrFail();

        $order->markExpiredIfOverdue();

        return new OrderResource($order);
    }

    public function update(Request $request, string $orderNumber): OrderResource
    {
        $validated = $request->validate([
            'courier' => ['nullable', 'string', 'max:255'],
            'waybill_number' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'tracking_status' => ['nullable', 'string', 'max:50'],
        ]);

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();
        $sellerId = $validated['seller_id'];
        unset($validated['seller_id']);
        abort_unless($order->items()->where('seller_id', $sellerId)->exists(), 422);
        OrderSellerTracking::updateOrCreate(['order_id' => $order->id, 'seller_id' => $sellerId], $validated);

        return new OrderResource($order->refresh()->load('sellerTrackings'));
    }
}
