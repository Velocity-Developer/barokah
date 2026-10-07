<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The variations of one product (needs variationOptions and variants
 * loaded). Shared by the storefront product payload and the edit forms.
 *
 * @mixin Product
 */
class ProductVariationsResource extends JsonResource
{
    /**
     * Variation types with their options, and every variant with the option
     * positions it combines, for the product page picker and the forms.
     *
     * @return array{types: list<array<string, mixed>>, variants: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        $flashSale = $this->activeFlashSale();
        $options = $this->variationOptions->groupBy('level');
        $positions = $this->variationOptions->mapWithKeys(fn (ProductVariationOption $option): array => [
            $option->id => $options[$option->level]->search(fn (ProductVariationOption $candidate): bool => $candidate->id === $option->id),
        ]);
        $names = $this->variationOptions->pluck('name', 'id');

        return [
            'types' => collect($this->variation_names)->values()->map(fn (string $name, int $index): array => [
                'name' => $name,
                'options' => ($options[$index + 1] ?? collect())->values()->map(fn (ProductVariationOption $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'image_url' => $option->image_url,
                ])->all(),
            ])->all(),
            'variants' => $this->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'options' => array_values(array_filter(
                    [$positions[$variant->option1_id] ?? null, $variant->option2_id !== null ? ($positions[$variant->option2_id] ?? null) : null],
                    fn ($position): bool => $position !== null,
                )),
                'label' => collect([$names[$variant->option1_id] ?? null, $names[$variant->option2_id] ?? null])->filter()->implode(', '),
                'price' => $variant->price,
                'effective_price' => number_format($this->priceFor($variant, $flashSale), 2, '.', ''),
                'stock' => $variant->stock,
                'weight_grams' => $variant->weight_grams,
            ])->all(),
        ];
    }
}
