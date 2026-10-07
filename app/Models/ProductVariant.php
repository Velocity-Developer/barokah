<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyable combination of variation options (e.g. Black / M) with its own
 * price, stock and optional weight (null falls back to the product weight).
 *
 * @property int $id
 * @property int $product_id
 * @property int $option1_id
 * @property int|null $option2_id
 * @property string $price
 * @property int $stock
 * @property int|null $weight_grams
 */
#[Fillable(['product_id', 'option1_id', 'option2_id', 'price', 'stock', 'weight_grams'])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'weight_grams' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariationOption, $this>
     */
    public function option1(): BelongsTo
    {
        return $this->belongsTo(ProductVariationOption::class, 'option1_id');
    }

    /**
     * @return BelongsTo<ProductVariationOption, $this>
     */
    public function option2(): BelongsTo
    {
        return $this->belongsTo(ProductVariationOption::class, 'option2_id');
    }

    /**
     * Shopper-facing name of the combination, e.g. "Black, M".
     */
    public function label(): string
    {
        return collect([$this->option1?->name, $this->option2?->name])->filter()->implode(', ');
    }
}
