<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Marketplace product with seller ownership (spec §10.2/§13).
 *
 * @property int $id
 * @property int $seller_id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $price
 * @property int $stock
 * @property int $weight_grams
 * @property ProductStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'seller_id',
    'category_id',
    'name',
    'slug',
    'description',
    'price',
    'stock',
    'weight_grams',
    'status',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'weight_grams' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    /**
     * Only products visible to public browsing.
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function latestReviews(): HasMany
    {
        return $this->reviews()->latest();
    }

    /**
     * @return HasMany<FlashSale, $this>
     */
    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_products')->withTimestamps();
    }

    public function activeFlashSale(): ?FlashSale
    {
        if ($this->relationLoaded('flashSales')) {
            return $this->flashSales->first(fn (FlashSale $sale): bool => $sale->isActive());
        }

        return $this->flashSales()->active()->first();
    }

    /**
     * Use slug for public route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
