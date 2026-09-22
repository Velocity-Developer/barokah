<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
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
            'products_count' => $this->whenCounted('products'),
            'active_products_count' => $this->whenHas('active_products_count', fn () => (int) $this->active_products_count),
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'image_url' => $this->whenLoaded('products', function (): ?string {
                $images = $this->products->first()?->images;

                return $images?->firstWhere('is_primary', true)?->url ?? $images?->first()?->url;
            }),
        ];
    }
}
