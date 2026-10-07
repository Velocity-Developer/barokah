<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saves the variations a seller or admin builds in the product form.
 *
 * The form posts `variations` as a JSON string (the form is multipart for
 * photos) shaped like:
 *
 *   {"types": [{"name": "Colour", "options": [{"id": 3, "name": "Black", "remove_image": false}]},
 *              {"name": "Size", "options": [{"id": null, "name": "M"}]}],
 *    "variants": [{"options": [0, 0], "price": "25.00", "stock": 4, "weight_grams": null}]}
 *
 * Variants point at options by their position in each type, and the list
 * must hold every combination exactly once. Photos for first-type options
 * arrive as files `variation_images[<option position>]`.
 */
class ProductVariationService
{
    public const MAX_TYPES = 2;

    public const MAX_OPTIONS = 20;

    public const MAX_VARIANTS = 200;

    /**
     * Decode and validate the posted payload. Returns null when the form did
     * not send variations at all, and an empty `types` list to remove them.
     *
     * @param  array<int|string, UploadedFile>  $files
     * @return array{types: list<array<string, mixed>>, variants: list<array<string, mixed>>}|null
     */
    public function parse(?string $json, array $files = []): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['variations' => 'The variations could not be read. Please try again.']);
        }

        $payload = ['types' => array_values($payload['types'] ?? []), 'variants' => array_values($payload['variants'] ?? [])];

        if ($payload['types'] === []) {
            return ['types' => [], 'variants' => []];
        }

        Validator::make(['payload' => $payload, 'variation_images' => $files], [
            'payload.types' => ['array', 'max:'.self::MAX_TYPES],
            'payload.types.*.name' => ['required', 'string', 'max:30'],
            'payload.types.*.options' => ['required', 'array', 'min:1', 'max:'.self::MAX_OPTIONS],
            'payload.types.*.options.*.id' => ['nullable', 'integer'],
            'payload.types.*.options.*.name' => ['required', 'string', 'max:50'],
            'payload.types.*.options.*.remove_image' => ['nullable', 'boolean'],
            'payload.variants' => ['required', 'array', 'max:'.self::MAX_VARIANTS],
            'payload.variants.*.options' => ['required', 'array'],
            'payload.variants.*.options.*' => ['integer', 'min:0'],
            'payload.variants.*.price' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payload.variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'payload.variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variation_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'payload.types.max' => 'A product can have at most '.self::MAX_TYPES.' variation types.',
            'payload.types.*.name.required' => 'Give every variation type a name, e.g. Colour or Size.',
            'payload.types.*.options.required' => 'Add at least one option to every variation type.',
            'payload.types.*.options.min' => 'Add at least one option to every variation type.',
            'payload.types.*.options.max' => 'A variation type can have at most '.self::MAX_OPTIONS.' options.',
            'payload.types.*.options.*.name.required' => 'Fill in every option name or remove the empty option.',
            'payload.variants.max' => 'Too many combinations: at most '.self::MAX_VARIANTS.' are allowed.',
            'payload.variants.*.price.required' => 'Fill in the price of every variation.',
            'payload.variants.*.price.min' => 'Every variation needs a price above zero.',
            'payload.variants.*.stock.required' => 'Fill in the stock of every variation (0 is fine).',
            'variation_images.*.image' => 'Variation photos must be JPG, PNG or WebP images.',
            'variation_images.*.mimes' => 'Variation photos must be JPG, PNG or WebP images.',
            'variation_images.*.max' => 'Each variation photo must be 2 MB or smaller.',
        ])->after(function ($validator) use ($payload): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $typeNames = array_map(fn (array $type): string => Str::lower(trim($type['name'])), $payload['types']);
            if (count($typeNames) !== count(array_unique($typeNames))) {
                $validator->errors()->add('payload', 'The two variation types need different names.');
            }

            foreach ($payload['types'] as $type) {
                $names = array_map(fn (array $option): string => Str::lower(trim($option['name'])), $type['options']);
                if (count($names) !== count(array_unique($names))) {
                    $validator->errors()->add('payload', 'Options of "'.trim($type['name']).'" must have different names.');
                }
            }

            $counts = array_map(fn (array $type): int => count($type['options']), $payload['types']);
            $seen = [];
            foreach ($payload['variants'] as $variant) {
                $positions = array_values($variant['options']);
                $valid = count($positions) === count($counts);
                foreach ($positions as $level => $position) {
                    $valid = $valid && $position < ($counts[$level] ?? 0);
                }
                if (! $valid) {
                    $validator->errors()->add('payload', 'A variation points at an option that does not exist. Please reload the page.');

                    return;
                }
                $seen[implode('-', $positions)] = true;
            }

            if (count($seen) !== count($payload['variants']) || count($seen) !== (int) array_product($counts)) {
                $validator->errors()->add('payload', 'Every combination of options needs exactly one price and stock row.');
            }

            if (array_product($counts) > self::MAX_VARIANTS) {
                $validator->errors()->add('payload', 'Too many combinations: at most '.self::MAX_VARIANTS.' are allowed.');
            }
        })->setAttributeNames(['variation_images.*' => 'variation photo'])
            ->validate();

        return $payload;
    }

    /**
     * Replace the product's variations with the parsed payload, then keep
     * products.price/stock in step (cheapest variant, total stock).
     *
     * @param  array{types: list<array<string, mixed>>, variants: list<array<string, mixed>>}  $payload
     * @param  array<int|string, UploadedFile>  $files
     */
    public function sync(Product $product, array $payload, array $files = []): void
    {
        if ($payload['types'] === []) {
            $this->clear($product);

            return;
        }

        $existing = $product->variationOptions()->get()->keyBy('id');
        $options = [];
        $kept = [];

        foreach ($payload['types'] as $typeIndex => $type) {
            $level = $typeIndex + 1;

            foreach (array_values($type['options']) as $position => $data) {
                $option = $existing->get((int) ($data['id'] ?? 0));
                $option = $option !== null && $option->level === $level ? $option : new ProductVariationOption(['product_id' => $product->id]);
                $option->fill(['level' => $level, 'name' => trim($data['name']), 'sort_order' => $position]);

                $file = $level === 1 ? ($files[$position] ?? null) : null;
                if ($file instanceof UploadedFile || $level !== 1 || ! empty($data['remove_image'])) {
                    $this->deleteImage($option);
                    $option->image_path = $file instanceof UploadedFile ? $file->store('products/variations', 'public') : null;
                }

                $option->save();
                $options[$typeIndex][$position] = $option->id;
                $kept[] = $option->id;
            }
        }

        $existingVariants = $product->variants()->get()->keyBy(fn (ProductVariant $variant): string => $variant->option1_id.'-'.$variant->option2_id);
        $keptVariants = [];

        foreach ($payload['variants'] as $data) {
            $positions = array_values($data['options']);
            $option1 = $options[0][$positions[0]];
            $option2 = isset($positions[1]) ? $options[1][$positions[1]] : null;

            $variant = $existingVariants->get($option1.'-'.$option2) ?? new ProductVariant(['product_id' => $product->id]);
            $variant->fill([
                'option1_id' => $option1,
                'option2_id' => $option2,
                'price' => $data['price'],
                'stock' => (int) $data['stock'],
                'weight_grams' => isset($data['weight_grams']) && $data['weight_grams'] !== '' ? (int) $data['weight_grams'] : null,
            ])->save();
            $keptVariants[] = $variant->id;
        }

        $product->variants()->whereKeyNot($keptVariants)->delete();
        $removed = $product->variationOptions()->whereKeyNot($kept)->get();
        $removed->each(fn (ProductVariationOption $option) => $this->deleteImage($option));
        $product->variationOptions()->whereKey($removed->modelKeys())->delete();

        $variants = $product->variants()->get();
        $product->forceFill([
            'variation_names' => array_map(fn (array $type): string => trim($type['name']), $payload['types']),
            'price' => $variants->min(fn (ProductVariant $variant): float => (float) $variant->price),
            'stock' => $variants->sum('stock'),
        ])->save();
    }

    /**
     * Remove every variation (and its photos) so the product sells as one
     * item again at its own price and stock.
     */
    public function clear(Product $product): void
    {
        $this->deleteImages($product);
        $product->variants()->delete();
        $product->variationOptions()->delete();
        $product->forceFill(['variation_names' => null])->save();
    }

    /**
     * Delete the stored option photos of a product (before it is deleted).
     */
    public function deleteImages(Product $product): void
    {
        $product->variationOptions()->whereNotNull('image_path')->get()
            ->each(fn (ProductVariationOption $option) => $this->deleteImage($option));
    }

    protected function deleteImage(ProductVariationOption $option): void
    {
        if ($option->image_path !== null) {
            Storage::disk('public')->delete($option->image_path);
        }
    }
}
