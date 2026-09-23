<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\Product;
use App\Services\CurrencyFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The seller's own flash sales, listed the same way as the admin page but
 * scoped to this store's products.
 */
class FlashSaleController extends Controller
{
    private const STATUSES = ['active', 'scheduled', 'sold_out', 'ended'];

    public function index(Request $request, CurrencyFormatter $currency): Response
    {
        $sellerId = $request->user()->load('seller')->seller?->id;

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'sort' => ['nullable', Rule::in(['starts_desc', 'starts_asc', 'ends_asc', 'sold_desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $filtered = FlashSale::query()
            ->whereHas('product', fn (Builder $query) => $query->where('seller_id', $sellerId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->whereHas('product', fn (Builder $product) => $product->where('name', 'like', $like));
            });

        $statusCounts = collect(self::STATUSES)
            ->mapWithKeys(fn (string $status): array => [$status => $this->whereStatus(clone $filtered, $status)->count()]);

        $sales = $filtered
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $this->whereStatus($query, $status))
            ->with(['product:id,name,slug,price,seller_id', 'product.images'])
            ->when(($validated['sort'] ?? 'starts_desc'), fn (Builder $query, string $sort) => match ($sort) {
                'starts_asc' => $query->orderBy('starts_at'),
                'ends_asc' => $query->orderBy('ends_at'),
                'sold_desc' => $query->orderByDesc('quantity_sold'),
                default => $query->orderByDesc('starts_at'),
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FlashSale $sale): array => [
                'id' => $sale->id,
                'product' => $sale->product === null ? null : [
                    'id' => $sale->product->id,
                    'name' => $sale->product->name,
                    'image' => $sale->product->images->firstWhere('is_primary', true)?->url ?? $sale->product->images->first()?->url,
                    'store' => null,
                ],
                'price_formatted' => $currency->format((float) $sale->price),
                'normal_price_formatted' => $sale->product ? $currency->format((float) $sale->product->price) : null,
                'discount_label' => $sale->discount_type === 'percentage'
                    ? rtrim(rtrim(number_format((float) $sale->discount_value, 2), '0'), '.').'% off'
                    : 'Fixed price',
                'quantity' => $sale->quantity,
                'quantity_sold' => $sale->quantity_sold,
                'starts_at' => $sale->starts_at?->toIso8601String(),
                'ends_at' => $sale->ends_at?->toIso8601String(),
                'status' => $this->statusOf($sale),
            ]);

        return Inertia::render('Seller/FlashSales/Index', [
            'flashSales' => $sales,
            'filters' => ['search' => $search, 'status' => $validated['status'] ?? '', 'sort' => $validated['sort'] ?? 'starts_desc'],
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Seller/FlashSales/Create', [
            'products' => $this->productOptions($request->user()->load('seller')->seller?->id),
        ]);
    }

    public function edit(Request $request, FlashSale $flashSale): Response
    {
        $flashSale->load('product:id,name,price,stock,seller_id');

        // Ownership is checked here so one seller cannot open another's sale.
        abort_unless($flashSale->product?->seller_id === $request->user()->load('seller')->seller?->id, 404);

        return Inertia::render('Seller/FlashSales/Edit', [
            'flashSale' => [
                'id' => $flashSale->id,
                'discount_type' => $flashSale->discount_type,
                'discount_value' => (float) $flashSale->discount_value,
                'quantity' => $flashSale->quantity,
                'quantity_sold' => $flashSale->quantity_sold,
                'starts_at' => $flashSale->starts_at?->toIso8601String(),
                'ends_at' => $flashSale->ends_at?->toIso8601String(),
                'status' => $this->statusOf($flashSale),
                'product' => [
                    'id' => $flashSale->product->id,
                    'name' => $flashSale->product->name,
                    'price' => (float) $flashSale->product->price,
                    'stock' => $flashSale->product->stock,
                    'store' => null,
                ],
            ],
        ]);
    }

    /**
     * @param  Builder<FlashSale>  $query
     * @return Builder<FlashSale>
     */
    private function whereStatus(Builder $query, string $status): Builder
    {
        $now = now();

        return match ($status) {
            'scheduled' => $query->where('starts_at', '>', $now),
            'ended' => $query->where('ends_at', '<=', $now),
            'sold_out' => $query->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->whereColumn('quantity_sold', '>=', 'quantity'),
            default => $query->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->whereColumn('quantity_sold', '<', 'quantity'),
        };
    }

    private function statusOf(FlashSale $sale): string
    {
        return match (true) {
            $sale->starts_at->isFuture() => 'scheduled',
            $sale->ends_at->lte(now()) => 'ended',
            $sale->remainingQuantity() === 0 => 'sold_out',
            default => 'active',
        };
    }

    /**
     * @return list<array{id: int, name: string, price: float, stock: int, store: null}>
     */
    private function productOptions(?int $sellerId): array
    {
        return Product::query()
            ->where('seller_id', $sellerId)
            ->where('status', ProductStatus::Active)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'stock'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'stock' => $product->stock,
                'store' => null,
            ])
            ->all();
    }
}
