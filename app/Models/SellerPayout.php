<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A seller's request to be paid for delivered orders. Amounts are frozen
 * when the request is made, so later commission changes do not touch it.
 *
 * @property int $id
 * @property string $reference
 * @property int $seller_id
 * @property PayoutStatus $status
 * @property string $currency_code
 * @property string $product_amount
 * @property string $shipping_amount
 * @property string $commission_rate
 * @property string $commission_amount
 * @property string $net_amount
 * @property int $orders_count
 * @property string $bank_account
 * @property string|null $seller_note
 * @property string|null $admin_note
 * @property string|null $transfer_reference
 * @property string|null $proof_path
 * @property int|null $processed_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'reference', 'seller_id', 'status', 'currency_code', 'product_amount', 'shipping_amount', 'commission_rate',
    'commission_amount', 'net_amount', 'orders_count', 'bank_account', 'seller_note', 'admin_note',
    'transfer_reference', 'proof_path', 'processed_by', 'approved_at', 'paid_at', 'rejected_at',
])]
class SellerPayout extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'product_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'orders_count' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Per-order shipments this payout pays for.
     *
     * @return HasMany<OrderSellerTracking, $this>
     */
    public function trackings(): HasMany
    {
        return $this->hasMany(OrderSellerTracking::class, 'payout_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
