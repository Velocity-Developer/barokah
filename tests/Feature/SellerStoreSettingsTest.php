<?php

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function settingsSeller(SellerStatus $status = SellerStatus::Active): User
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $owner->id, 'status' => $status]);

    return $owner->refresh();
}

it('links to the live store page, and locks a suspended store out', function () {
    $owner = settingsSeller();

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Settings')
            ->where('seller.public_url', route('sellers.show', $owner->seller->slug))
            ->where('seller.status', 'active'));

    // A suspended store loses the seller area altogether.
    $owner->seller->update(['status' => SellerStatus::Suspended]);

    $this->withoutVite()->actingAs($owner->refresh())
        ->get(route('seller.settings'))
        ->assertForbidden();
});

it('saves the store profile, photo and banner', function () {
    Storage::fake('public');
    $owner = settingsSeller();

    $this->actingAs($owner)
        ->post('/api/v1/seller/settings', [
            '_method' => 'PUT',
            'store_name' => 'Kedai Baharu',
            'whatsapp' => '60123456789',
            'bank_account' => 'Maybank a.n. Ahmad 1234567890',
            'profile_photo' => UploadedFile::fake()->image('photo.jpg'),
            'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 300),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $seller = $owner->seller->fresh();

    expect($seller->store_name)->toBe('Kedai Baharu')
        ->and($seller->whatsapp)->toBe('60123456789');

    Storage::disk('public')->assertExists($seller->profile_photo_path);
    Storage::disk('public')->assertExists($seller->banner_path);
});
