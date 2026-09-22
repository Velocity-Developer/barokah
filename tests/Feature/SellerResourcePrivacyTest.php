<?php

use App\Enums\SellerStatus;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function sellerWithBankAccount(): Seller
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);

    return Seller::factory()->create([
        'user_id' => $owner->id,
        'status' => SellerStatus::Active,
        'bank_account' => 'Maybank 1234567890',
    ]);
}

it('hides the bank account on the public store page', function () {
    $seller = sellerWithBankAccount();

    $this->get(route('sellers.show', $seller->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seller.data.id', $seller->id)
            ->missing('seller.data.bank_account'));

    $this->actingAs(User::factory()->create())
        ->get(route('sellers.show', $seller->slug))
        ->assertInertia(fn (Assert $page) => $page->missing('seller.data.bank_account'));
});

it('hides the bank account of the seller embedded in a product', function () {
    $seller = sellerWithBankAccount();
    $product = Product::factory()->create(['seller_id' => $seller->id]);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.data.seller.id', $seller->id)
            ->missing('product.data.seller.bank_account'));
});

it('still returns the bank account to the store owner', function () {
    $seller = sellerWithBankAccount();

    $this->actingAs($seller->user)
        ->putJson('/api/v1/seller/settings', ['city' => $seller->city, 'state' => $seller->state])
        ->assertOk()
        ->assertJsonPath('data.bank_account', 'Maybank 1234567890');
});

it('uses the owner photo and banner on the store page without exposing the owner', function () {
    $seller = sellerWithBankAccount();
    $seller->user->forceFill([
        'profile_photo_path' => 'users/photos/owner.jpg',
        'banner_path' => 'users/banners/owner.jpg',
    ])->save();

    $this->get(route('sellers.show', $seller->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seller.data.profile_photo_url', fn (string $url) => str_ends_with($url, 'users/photos/owner.jpg'))
            ->where('seller.data.banner_url', fn (string $url) => str_ends_with($url, 'users/banners/owner.jpg'))
            ->missing('seller.data.user')
            ->missing('seller.data.email'));
});

it('prefers the store photo over the owner photo', function () {
    $seller = sellerWithBankAccount();
    $seller->update(['profile_photo_path' => 'sellers/store.jpg']);
    $seller->user->forceFill(['profile_photo_path' => 'users/photos/owner.jpg'])->save();

    $this->get(route('sellers.show', $seller->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seller.data.profile_photo_url', fn (string $url) => str_ends_with($url, 'sellers/store.jpg'))
            ->where('seller.data.banner_url', null));
});
