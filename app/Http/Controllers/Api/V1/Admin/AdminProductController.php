<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin product management (spec §17): CRUD all products, status
 * moderation, category assignment.
 *
 * Sellers manage their own products via SellerProductController; admins
 * may moderate any product here (ProductPolicy grants admin override).
 */
class AdminProductController extends Controller
{
    /** @var array<string, array{0: string, 1: string}> */
    private const SORTS = [
        'latest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'name_asc' => ['name', 'asc'],
        'name_desc' => ['name', 'desc'],
        'price_asc' => ['price', 'asc'],
        'price_desc' => ['price', 'desc'],
        'stock_asc' => ['stock', 'asc'],
        'stock_desc' => ['stock', 'desc'],
        'sold_desc' => ['sold_count', 'desc'],
    ];

    private const LOW_STOCK = 5;

    /**
     * Paginated product list with search, filters, sorting and status counts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'seller_id' => ['nullable', 'integer', Rule::exists('sellers', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'search' => ['nullable', 'string', 'max:255'],
            'stock' => ['nullable', Rule::in(['in_stock', 'low', 'out'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $filtered = Product::query()
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $inner->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereHas('seller', fn (Builder $seller) => $seller->where('store_name', 'like', $like))
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
            }))
            ->when($validated['seller_id'] ?? null, fn (Builder $query, int $sellerId) => $query->where('seller_id', $sellerId))
            ->when($validated['category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($validated['stock'] ?? null, fn (Builder $query, string $stock) => match ($stock) {
                'out' => $query->where('stock', '<=', 0),
                'low' => $query->whereBetween('stock', [1, self::LOW_STOCK]),
                default => $query->where('stock', '>', 0),
            });

        // Counts per status for the tabs, respecting every filter except the status itself.
        $statusCounts = (clone $filtered)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        [$column, $direction] = self::SORTS[$validated['sort'] ?? 'latest'];

        $products = $filtered
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['seller', 'category', 'images', 'flashSales'])
            ->withSum('orderItems as sold_count', 'quantity')
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return ProductResource::collection($products)->additional([
            'status_counts' => $statusCounts,
            'status_counts_total' => $statusCounts->sum(),
        ]);
    }

    /**
     * Show any product with relations.
     */
    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['seller', 'category', 'images']));
    }

    /**
     * Update any product: all fields + image management.
     *
     * Follows the same image handling pattern as SellerProductController
     * so admins can fully manage any product.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($request, $product, $validated) {
            if (array_key_exists('name', $validated)) {
                $product->slug = $this->uniqueSlug($validated['name'], $product->id);
            }

            $product->fill($validated);
            $product->save();

            if (! empty($validated['remove_image_ids'])) {
                $images = $product->images()->whereIn('id', $validated['remove_image_ids'])->get();

                foreach ($images as $image) {
                    Storage::disk('public')->delete($image->path);
                    $image->delete();
                }
            }

            $this->storeImages($product->refresh(), $request->file('images', []));

            return $product->load(['seller', 'category', 'images']);
        });

        return new ProductResource($product);
    }

    /**
     * Remove any product and its image files.
     */
    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        DB::transaction(function () use ($product): void {
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->path);
            }

            $product->delete();
        });

        return response()->noContent();
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base === '' ? Str::random(8) : $base;
        $candidate = $slug;
        $counter = 2;

        while (Product::query()->where('slug', $candidate)->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    protected function storeImages(Product $product, array $files): void
    {
        if ($files === []) {
            return;
        }

        $startOrder = (int) $product->images()->max('sort_order') + 1;
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach (array_values($files) as $index => $file) {
            $product->images()->create([
                'path' => $file->store('products', 'public'),
                'sort_order' => $startOrder + $index,
                'is_primary' => ! $hasPrimary && $index === 0,
            ]);
        }
    }
}
