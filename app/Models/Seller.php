<?php

namespace App\Models;

use App\Enums\SellerStatus;
use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property string $store_name
 * @property string $slug
 * @property string|null $description
 * @property string|null $profile_photo_path
 * @property string|null $banner_path
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $store_location
 * @property string|null $bank_account
 * @property string|null $state
 * @property string|null $city
 * @property SellerStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'store_name', 'slug', 'description', 'profile_photo_path', 'phone', 'whatsapp', 'store_location', 'bank_account', 'state', 'city', 'status'])]
class Seller extends Model
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['profile_photo_url', 'banner_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SellerStatus::class,
        ];
    }

    /**
     * Public URL for the store profile photo.
     *
     * @return Attribute<string|null, never>
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->publicUrl($this->profile_photo_path));
    }

    /**
     * Public URL for the store's own banner.
     *
     * @return Attribute<string|null, never>
     */
    protected function bannerUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->publicUrl($this->banner_path));
    }

    /**
     * Store photo shown to shoppers: the store's own, else the owner's photo.
     */
    public function displayPhotoUrl(): ?string
    {
        return $this->profile_photo_url ?? $this->user?->profile_photo_url;
    }

    /**
     * Store banner shown to shoppers: the store's own, else the owner's banner.
     */
    public function displayBannerUrl(): ?string
    {
        return $this->banner_url ?? $this->user?->banner_url;
    }

    private function publicUrl(?string $path): ?string
    {
        return $path !== null && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Reviews left on this store's products.
     *
     * @return HasManyThrough<ProductReview, Product, $this>
     */
    public function productReviews(): HasManyThrough
    {
        return $this->hasManyThrough(ProductReview::class, Product::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seller_follows')->withTimestamps();
    }
}
