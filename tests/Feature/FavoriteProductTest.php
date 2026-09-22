<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login when favoriting a product', function () {
    $product = Product::factory()->create();

    $this->post(route('products.favorite', $product))->assertRedirect(route('login'));

    $this->assertDatabaseCount('favorite_products', 0);
});

it('lets a user favorite an active product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->from(route('products.show', $product->slug))
        ->post(route('products.favorite', $product))
        ->assertRedirect(route('products.show', $product->slug));

    $this->assertDatabaseHas('favorite_products', ['user_id' => $user->id, 'product_id' => $product->id]);
});

it('rejects favoriting products that are not active', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['status' => ProductStatus::Draft]);

    $this->actingAs($user)->post(route('products.favorite', $product))->assertNotFound();

    $this->assertDatabaseCount('favorite_products', 0);
});

it('removes the favorite when toggled again', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)->post(route('products.favorite', $product));
    $this->actingAs($user)->post(route('products.favorite', $product));

    $this->assertDatabaseCount('favorite_products', 0);
});

it('shows the favorite state on the product page', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $user->favoriteProducts()->attach($product);

    $this->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('product.data.is_favorited', false));

    $this->actingAs($user)
        ->get(route('products.show', $product->slug))
        ->assertInertia(fn (Assert $page) => $page->where('product.data.is_favorited', true));
});

it('lists only active favorite products on the profile page', function () {
    $user = User::factory()->create();
    $active = Product::factory()->create();
    $archived = Product::factory()->create(['status' => ProductStatus::Archived]);
    $user->favoriteProducts()->attach([$active->id, $archived->id]);

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('favoriteProducts.data', 1)
            ->where('favoriteProducts.data.0.id', $active->id)
            ->where('favoriteProducts.data.0.is_favorited', true));
});
