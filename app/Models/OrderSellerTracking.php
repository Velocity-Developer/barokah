<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'seller_id', 'shipping_fee', 'shipping_provider', 'courier', 'waybill_number', 'tracking_url', 'tracking_status', 'notes', 'received_at', 'packed_at', 'picked_up_at', 'delivered_at', 'delivery_photo_path'])]
class OrderSellerTracking extends Model
{
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'packed_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
