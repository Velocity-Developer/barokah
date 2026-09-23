<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
 * Seller-scoped product CRUD (spec §11.3). Every query is scoped to the
 * current seller; ownership is additionally enforced by ProductPolicy.
 */
class SellerProductController extends Controller
{
    /** @var array<string, array{0: string, 1: string}> */
    private const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'name_asc' => ['name', 'asc'],
        'price_desc' => ['price', 'desc'],
        'price_asc' => ['price', 'asc'],
        'stock_asc' => ['stock', 'asc'],
    ];

    /**
     * List the current seller's products with search, status tabs, a category
     * filter, sorting and the counts the tabs show.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $filtered = Product::query()
            ->whereBelongsTo($request->user()->seller)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like));
            })
            ->when($validated['category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId));

        $statusCounts = (clone $filtered)
            ->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        [$column, $direction] = self::SORTS[$validated['sort'] ?? 'newest'];

        $products = $filtered
            ->when($validated['status'] ?? null, fn (Builder $query, ProductStatus|string $status) => $query->where('status', $status instanceof \BackedEnum ? $status->value : $status))
            // flashSales is loaded so the list can show the sale price without
            // one query per row.
            ->with(['category', 'images', 'flashSales'])
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
     * Create a product owned by the current seller.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($request, $validated) {
            /** @var Product $product */
            $product = $request->user()->seller->products()->create([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['name']),
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock' => $validated['stock'],
                'weight_grams' => $validated['weight_grams'] ?? 0,
                'status' => $validated['status'],
            ]);

            $this->storeImages($product, $request->file('images', []));

            return $product->load(['seller', 'category', 'images', 'flashSales']);
        });

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /**
     * Show a single product owned by the current seller.
     */
    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(['seller', 'category', 'images']));
    }

    /**
     * Update a product owned by the current seller.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($request, $product, $validated) {
            if (array_key_exists('slug', $validated)) {
                $validated['slug'] = $validated['slug'] === null || $validated['slug'] === ''
                    ? $this->uniqueSlug($validated['name'] ?? $product->name, $product->id)
                    : $this->uniqueSlug($validated['slug'], $product->id);
            }

            $product->fill($validated);

            if (array_key_exists('name', $validated) && ! array_key_exists('slug', $validated)) {
                $product->slug = $this->uniqueSlug($validated['name'], $product->id);
            }

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
     * Delete a product owned by the current seller.
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
