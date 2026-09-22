<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Store photo and banner uploads shared by the seller and admin store forms.
 */
trait UpdatesSellerMedia
{
    /**
     * @return array<string, list<string>>
     */
    protected function sellerMediaRules(): array
    {
        return [
            'profile_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_photo' => ['sometimes', 'nullable', 'boolean'],
            'banner' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_banner' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Drop the media keys from validated input so they are not mass assigned.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function withoutSellerMedia(array $validated): array
    {
        return array_diff_key($validated, array_flip(array_keys($this->sellerMediaRules())));
    }

    /**
     * Replace or remove the store photo and banner; old files are deleted.
     */
    protected function applySellerMedia(Request $request, Seller $seller): void
    {
        $media = [
            'profile_photo_path' => ['file' => 'profile_photo', 'remove' => 'remove_profile_photo', 'folder' => 'sellers'],
            'banner_path' => ['file' => 'banner', 'remove' => 'remove_banner', 'folder' => 'sellers/banners'],
        ];

        foreach ($media as $column => $input) {
            $file = $request->file($input['file']);

            if ($file === null && ! $request->boolean($input['remove'])) {
                continue;
            }

            if ($seller->{$column} !== null && $seller->{$column} !== '') {
                Storage::disk('public')->delete($seller->{$column});
            }

            $seller->{$column} = $file?->store($input['folder'], 'public');
        }
    }
}
