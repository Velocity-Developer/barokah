<?php

namespace App\Models;

use Database\Factories\FlashSaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'price', 'discount_type', 'discount_value', 'quantity', 'quantity_sold', 'starts_at', 'ends_at'])]
class FlashSale extends Model
{
    /** @use HasFactory<FlashSaleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'quantity' => 'integer',
            'quantity_sold' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereColumn('quantity_sold', '<', 'quantity');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Promo price of the product, or of one of its variants. A percentage
     * sale takes the same percentage off the variant price; a fixed promo
     * price (set against the cheapest variant) keeps the same saving.
     */
    public function priceFor(Product $product, ?ProductVariant $variant = null): float
    {
        if ($variant === null) {
            return (float) $this->price;
        }

        $base = (float) $variant->price;
        $price = $this->discount_type === 'percentage'
            ? $base * (1 - (float) $this->discount_value / 100)
            : $base - max(0, (float) $product->price - (float) $this->price);

        return round(max(0.01, min($base, $price)), 2);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_sold);
    }

    public function isActive(): bool
    {
        return $this->starts_at->lte(now()) && $this->ends_at->gt(now()) && $this->remainingQuantity() > 0;
    }
}
