<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\SellerStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $is_admin
 * @property bool $is_active_as_seller
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $state
 * @property string|null $city
 * @property string|null $post_code
 * @property string|null $profile_photo_path
 * @property string|null $banner_path
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'address', 'state', 'city', 'post_code'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_active_as_seller' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->publicUrl($this->profile_photo_path));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function bannerUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->publicUrl($this->banner_path));
    }

    private function publicUrl(?string $path): ?string
    {
        return $path !== null && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isSeller(): bool
    {
        if ($this->is_active_as_seller !== true) {
            return false;
        }

        $status = $this->relationLoaded('seller')
            ? $this->seller?->status
            : $this->seller()->value('status');

        if ($status instanceof SellerStatus) {
            return $status === SellerStatus::Active;
        }

        // TBC (spec §24 item 6): seller approval workflow is undecided, so a
        // missing or unexpected status never grants the seller capability.
        return $status === SellerStatus::Active->value;
    }

    /**
     * @return HasOne<Seller, $this>
     */
    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    /**
     * @return BelongsToMany<Seller, $this>
     */
    public function followedSellers(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'seller_follows')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function favoriteProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'favorite_products')->withTimestamps();
    }

    public function followsSeller(Seller $seller): bool
    {
        return $this->followedSellers()->whereKey($seller->getKey())->exists();
    }

    public function hasFavorited(Product $product): bool
    {
        return $this->favoriteProducts()->whereKey($product->getKey())->exists();
    }
}
