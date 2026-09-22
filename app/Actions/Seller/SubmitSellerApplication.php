<?php

namespace App\Actions\Seller;

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A buyer applies to open a store. The store starts as Pending and only gets
 * seller access once an admin approves it (User::isSeller() requires Active).
 */
class SubmitSellerApplication
{
    /**
     * @param  array{store_name: string, description?: string|null}  $data
     */
    public function handle(User $user, array $data): Seller
    {
        return DB::transaction(function () use ($user, $data): Seller {
            /** @var Seller $seller */
            $seller = $user->seller()->create([
                'store_name' => $data['store_name'],
                'slug' => $this->uniqueSlug($data['store_name']),
                'description' => $data['description'] ?? null,
                'status' => SellerStatus::Pending,
            ]);

            $user->forceFill(['is_active_as_seller' => true])->save();

            return $seller;
        });
    }

    protected function uniqueSlug(string $storeName): string
    {
        $base = Str::slug($storeName);
        $slug = $base === '' ? Str::random(8) : $base;
        $candidate = $slug;
        $counter = 2;

        while (Seller::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
