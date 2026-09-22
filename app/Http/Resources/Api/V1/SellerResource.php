<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Seller */
class SellerResource extends JsonResource
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
            'store_name' => $this->store_name,
            'slug' => $this->slug,
            'description' => $this->description,
            // The store's own photo / banner, falling back to the owner's when the
            // owner is loaded; only these two URLs are taken from the user record.
            'profile_photo_url' => $this->relationLoaded('user') ? $this->displayPhotoUrl() : $this->profile_photo_url,
            'banner_url' => $this->relationLoaded('user') ? $this->displayBannerUrl() : $this->banner_url,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'store_location' => $this->store_location,
            'bank_account' => $this->when($this->canViewPrivateDetails($request), $this->bank_account),
            'state' => $this->state,
            'city' => $this->city,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count ?? 0,
            'followers_count' => $this->whenCounted('followers'),
            'products_count' => $this->whenCounted('products'),
            'active_products_count' => $this->whenHas('active_products_count', fn () => (int) $this->active_products_count),
            'owner' => $this->when(
                $this->relationLoaded('user') && $this->canViewPrivateDetails($request),
                fn (): ?array => $this->user ? ['name' => $this->user->name, 'email' => $this->user->email] : null,
            ),
            'joined_at' => $this->created_at?->toIso8601String(),
            'is_followed' => $this->whenHas('is_followed'),
        ];
    }

    /**
     * Payout details are only for the store owner and admins, never for
     * public pages that embed the seller (store page, product cards, etc.).
     */
    private function canViewPrivateDetails(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->isAdmin() || $user->id === $this->user_id);
    }
}
