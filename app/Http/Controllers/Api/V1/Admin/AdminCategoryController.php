<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin category management (spec §13/§17): full CRUD, unique slugs,
 * sort order. The self-referencing hierarchy stays TBC (spec §24
 * item 8); parent_id is accepted as nullable only.
 */
class AdminCategoryController extends Controller
{
    /** @var array<string, list<array{0: string, 1: string}>> */
    private const SORTS = [
        'position' => [['sort_order', 'asc'], ['name', 'asc']],
        'name_asc' => [['name', 'asc']],
        'name_desc' => [['name', 'desc']],
        'products_desc' => [['products_count', 'desc'], ['name', 'asc']],
        'newest' => [['created_at', 'desc']],
    ];

    /**
     * Categories with product counts, search, status filter and sorting.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $filtered = Category::query()->when($search !== '', function (Builder $query) use ($search): void {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('description', 'like', $like));
        });

        $statusCounts = [
            'active' => (clone $filtered)->where('is_active', true)->count(),
            'inactive' => (clone $filtered)->where('is_active', false)->count(),
        ];

        $categories = $filtered
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->withCount([
                'products',
                'products as active_products_count' => fn (Builder $query) => $query->where('status', ProductStatus::Active),
            ]);

        foreach (self::SORTS[$validated['sort'] ?? 'position'] as [$column, $direction]) {
            $categories->orderBy($column, $direction);
        }

        return CategoryResource::collection($categories->orderBy('id')->paginate((int) ($validated['per_page'] ?? 15))->withQueryString())
            ->additional([
                'status_counts' => $statusCounts,
                'status_counts_total' => array_sum($statusCounts),
            ]);
    }

    /**
     * Create a category with a unique slug.
     */
    public function store(Request $request): CategoryResource|JsonResponse
    {
        Gate::authorize('create', Category::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        unset($validated['image']);

        $category = Category::query()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['name']),
        ]);

        $this->applyImage($request, $category);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    /**
     * Update a category, regenerating the slug on rename.
     */
    public function update(Request $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'nullable', 'boolean'],
        ]);

        unset($validated['image'], $validated['remove_image']);

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = $validated['slug'] === null || $validated['slug'] === ''
                ? $this->uniqueSlug($validated['name'] ?? $category->name, $category->id)
                : $this->uniqueSlug($validated['slug'], $category->id);
        } elseif (array_key_exists('name', $validated)) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $category->id);
        }

        $category->fill($validated)->save();
        $this->applyImage($request, $category);

        return new CategoryResource($category->refresh());
    }

    /**
     * Replace or clear the category picture; the old file is deleted.
     */
    private function applyImage(Request $request, Category $category): void
    {
        $file = $request->file('image');

        if ($file === null && ! $request->boolean('remove_image')) {
            return;
        }

        if ($category->image_path !== null && $category->image_path !== '') {
            Storage::disk('public')->delete($category->image_path);
        }

        $category->forceFill(['image_path' => $file?->store('categories', 'public')])->save();
    }

    /**
     * Remove a category.
     */
    public function destroy(Category $category): Response|JsonResponse
    {
        Gate::authorize('delete', $category);

        // Products must always belong to a category (restrict on delete).
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            return response()->json([
                'message' => "This category still has {$productCount} ".str('product')->plural($productCount).'. Move them to another category or deactivate it instead.',
            ], 422);
        }

        $category->delete();

        return response()->noContent();
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base === '' ? Str::random(8) : $base;
        $candidate = $slug;
        $counter = 2;

        while (Category::query()->where('slug', $candidate)->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
