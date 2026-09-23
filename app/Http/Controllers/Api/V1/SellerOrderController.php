<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SellerOrderResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SellerOrderController extends Controller
{
    /** @var array<string, array{0: string, 1: string}> */
    private const SORTS = [
        'latest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'total_desc' => ['total', 'desc'],
        'total_asc' => ['total', 'asc'],
    ];

    /** Where the seller's own handover stands, on top of the order status. */
    private const FULFILMENTS = ['not_started', 'received', 'packed', 'picked_up', 'delivered'];

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

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'fulfilment' => ['nullable', Rule::in(self::FULFILMENTS)],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $filtered = Order::query()
            ->whereHas('items', fn (Builder $query) => $query->where('seller_id', $sellerId))
            ->when($search !== '', function (Builder $query) use ($search, $sellerId): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('order_number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_email', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhereHas('items', fn (Builder $item) => $item
                        ->where('seller_id', $sellerId)
                        ->where('product_name_snapshot', 'like', $like)));
            })
            ->when($validated['fulfilment'] ?? null, fn (Builder $query, string $fulfilment) => $this->applyFulfilment($query, $fulfilment, $sellerId));

        // Counts per status for the tabs, respecting every filter except the status itself.
        $statusCounts = (clone $filtered)
            ->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        [$column, $direction] = self::SORTS[$validated['sort'] ?? 'latest'];

        $orders = $filtered
            ->when($validated['status'] ?? null, fn (Builder $query, OrderStatus|string $status) => $query->where('status', $status instanceof \BackedEnum ? $status->value : $status))
            ->with([
                'items' => fn ($relation) => $relation->where('seller_id', $sellerId),
                'payment',
                'sellerTrackings' => fn ($relation) => $relation->where('seller_id', $sellerId),
            ])
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return SellerOrderResource::collection($orders)->additional([
            'status_counts' => $statusCounts,
            'status_counts_total' => $statusCounts->sum(),
        ]);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private function applyFulfilment(Builder $query, string $fulfilment, ?int $sellerId): Builder
    {
        $tracking = fn (Builder $inner) => $inner->where('seller_id', $sellerId);

        return $fulfilment === 'not_started'
            ? $query->whereDoesntHave('sellerTrackings', $tracking)
            : $query->whereHas('sellerTrackings', fn (Builder $inner) => $tracking($inner)->where('tracking_status', $fulfilment));
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
