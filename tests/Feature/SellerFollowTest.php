<?php

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login when following a seller', function () {
    $seller = Seller::factory()->create(['status' => SellerStatus::Active]);

    $this->post(route('sellers.follow', $seller))->assertRedirect(route('login'));

    $this->assertDatabaseCount('seller_follows', 0);
});

it('lets a user follow an active seller', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['status' => SellerStatus::Active]);

    $this->actingAs($user)
        ->from(route('sellers.show', $seller->slug))
        ->post(route('sellers.follow', $seller))
        ->assertRedirect(route('sellers.show', $seller->slug));

    $this->assertDatabaseHas('seller_follows', ['user_id' => $user->id, 'seller_id' => $seller->id]);
    expect($seller->followers()->count())->toBe(1);
});

it('unfollows when the seller is already followed', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['status' => SellerStatus::Active]);

    $this->actingAs($user)->post(route('sellers.follow', $seller));
    $this->actingAs($user)->post(route('sellers.follow', $seller));

    $this->assertDatabaseCount('seller_follows', 0);
});

it('does not follow inactive or missing sellers', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['status' => SellerStatus::Pending]);

    $this->actingAs($user)->post(route('sellers.follow', $seller))->assertNotFound();
    $this->actingAs($user)->post('/sellers/does-not-exist/follow')->assertNotFound();

    $this->assertDatabaseCount('seller_follows', 0);
});

it('shows followers count and follow state on the seller page', function () {
    $seller = Seller::factory()->create(['status' => SellerStatus::Active]);
    $follower = User::factory()->create();
    $seller->followers()->attach([$follower->id, User::factory()->create()->id]);

    $this->get(route('sellers.show', $seller->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seller.data.followers_count', 2)
            ->where('seller.data.is_followed', false));

    $this->actingAs($follower)
        ->get(route('sellers.show', $seller->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seller.data.followers_count', 2)
            ->where('seller.data.is_followed', true));
});

it('lists only active followed sellers on the profile page', function () {
    $user = User::factory()->create();
    $active = Seller::factory()->create(['status' => SellerStatus::Active]);
    $suspended = Seller::factory()->create(['status' => SellerStatus::Pending]);
    $user->followedSellers()->attach([$active->id, $suspended->id]);

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->has('followedSellers.data', 1)
            ->where('followedSellers.data.0.id', $active->id)
            ->where('followedSellers.data.0.followers_count', 1));
});

it('shows store stats and follow state on the product page', function () {
    $seller = Seller::factory()->create(['status' => SellerStatus::Active]);
    $product = Product::factory()->create(['seller_id' => $seller->id]);
    Product::factory()->create(['seller_id' => $seller->id, 'status' => ProductStatus::Draft]);
    $follower = User::factory()->create();
    $seller->followers()->attach($follower);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.data.seller.products_count', 1)
            ->where('product.data.seller.followers_count', 1)
            ->where('product.data.seller.is_followed', false)
            ->has('product.data.seller.joined_at')
            ->missing('product.data.seller.bank_account'));

    $this->actingAs($follower)
        ->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('product.data.seller.is_followed', true));
});
