<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'normal_price' => $this->price,
            'effective_price' => $this->activeFlashSale()?->price ?? $this->price,
            'flash_sale' => new FlashSaleResource($this->when($this->activeFlashSale() !== null, $this->activeFlashSale())),
            'flash_sale_active' => $this->activeFlashSale() !== null,
            'stock' => $this->stock,
            'sold_count' => $this->when($this->sold_count !== null, fn (): int => (int) $this->sold_count),
            'weight_grams' => $this->weight_grams,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'seller' => new SellerResource($this->whenLoaded('seller')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => $this->when(
                $this->relationLoaded('images') && $this->images->isNotEmpty(),
                fn (): ?string => $this->images->firstWhere('is_primary', true)?->url
                    ?? $this->images->first()?->url
            ),
            'created_at' => $this->created_at,
            'average_rating' => $this->reviews_avg_rating,
            'ratings_count' => $this->reviews_count,
            'reviews' => ProductReviewResource::collection($this->whenLoaded('latestReviews')),
            'is_favorited' => $this->whenHas('is_favorited'),
            'updated_at' => $this->updated_at,
        ];
    }
}
