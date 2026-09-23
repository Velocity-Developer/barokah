<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'state' => $this->state,
            'city' => $this->city,
            'post_code' => $this->post_code,
            'profile_photo_url' => $this->profile_photo_url,
            'is_admin' => $this->isAdmin(),
            'is_active_as_seller' => (bool) $this->is_active_as_seller,
            'is_seller' => $this->isSeller(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'joined_at' => $this->created_at?->toIso8601String(),
            // Present on the admin list, which counts orders and paid spend.
            'orders_count' => $this->whenHas('orders_count'),
            'total_spent' => $this->whenHas('total_spent', fn (mixed $total): float => (float) $total),
            'seller' => SellerResource::make($this->whenLoaded('seller')),
        ];
    }
}
