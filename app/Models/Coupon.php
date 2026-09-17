<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'code', 'name', 'description', 'owner_type', 'seller_id', 'discount_type', 'discount_value',
    'maximum_discount', 'minimum_spend', 'usage_limit', 'usage_count', 'per_user_limit',
    'starts_at', 'ends_at', 'allow_flash_sale', 'status',
])]
class Coupon extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'minimum_spend' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'allow_flash_sale' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isAvailable(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->status && $this->starts_at !== null && $this->ends_at !== null
            && $this->starts_at->lte($at) && $this->ends_at->gte($at)
            && ($this->usage_limit === null || $this->usage_count < $this->usage_limit);
    }
}
