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

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_sold);
    }

    public function isActive(): bool
    {
        return $this->starts_at->lte(now()) && $this->ends_at->gt(now()) && $this->remainingQuantity() > 0;
    }
}
