<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function detailAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('shows the product detail with stats', function () {
    $product = Product::factory()->create(['price' => 20, 'stock' => 3]);

    $this->withoutVite()
        ->actingAs(detailAdmin())
        ->get(route('admin.products.show', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Products/Show')
            ->where('product.id', $product->id)
            ->where('public_url', route('products.show', $product->slug))
            ->where('stats.sold', 0)
            ->where('stats.reviews', 0)
            ->where('flash_sale', null)
            ->has('recent_orders', 0));
});

it('hides the store link for products that are not active', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Draft]);

    $this->withoutVite()
        ->actingAs(detailAdmin())
        ->get(route('admin.products.show', $product))
        ->assertInertia(fn (Assert $page) => $page->where('public_url', null));
});

it('opens the product edit page', function () {
    $product = Product::factory()->create();

    $this->withoutVite()
        ->actingAs(detailAdmin())
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Products/Edit')->where('product.id', $product->id));
});
