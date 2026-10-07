<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One option of a product variation type, e.g. "Black" of "Colour".
 * `level` is 1 or 2 (the first or second variation type of the product);
 * only level-1 options are given photos in the forms.
 *
 * @property int $id
 * @property int $product_id
 * @property int $level
 * @property string $name
 * @property string|null $image_path
 * @property int $sort_order
 */
#[Fillable(['product_id', 'level', 'name', 'image_path', 'sort_order'])]
class ProductVariationOption extends Model
{
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
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
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        );
    }
}
