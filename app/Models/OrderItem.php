<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Order line with denormalized seller_id and product snapshots per spec
 * §10.2/§14.3. Seller dashboard scope filters on seller_id.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property string|null $variant_label
 * @property int $seller_id
 * @property string $product_name_snapshot
 * @property string $product_slug_snapshot
 * @property string $price_snapshot
 * @property int $quantity
 * @property string $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id',
    'product_id',
    'product_variant_id',
    'seller_id',
    'product_name_snapshot',
    'variant_label',
    'product_slug_snapshot',
    'price_snapshot',
    'quantity',
    'subtotal',
    'discount_amount',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Put the reserved quantity back on the product (and its variant) when
     * an unpaid order expires, fails or is cancelled. Callers run this
     * inside their transaction with the order row locked.
     */
    public function restoreStock(): void
    {
        if ($this->product_id === null) {
            return;
        }

        Product::query()->whereKey($this->product_id)->increment('stock', $this->quantity);

        if ($this->product_variant_id !== null) {
            ProductVariant::query()->whereKey($this->product_variant_id)->increment('stock', $this->quantity);
        }
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }
}
