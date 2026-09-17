<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlashSaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'price' => $this->price,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'quantity' => $this->quantity,
            'quantity_sold' => $this->quantity_sold,
            'remaining_quantity' => $this->remainingQuantity(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->isActive() ? 'active' : ($this->starts_at->isFuture() ? 'scheduled' : ($this->remainingQuantity() === 0 ? 'sold_out' : 'ended')),
        ];
    }
}
