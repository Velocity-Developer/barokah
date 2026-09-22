<?php

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares active sellers with homepage and excludes inactive sellers', function () {
    $active = Seller::factory()->create(['status' => SellerStatus::Active]);
    Seller::factory()->create(['status' => SellerStatus::Pending]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('sellers.data', 1)
            ->where('sellers.data.0.id', $active->id)
        );
});

it('ranks featured sellers and shares their store stats', function () {
    $empty = Seller::factory()->create(['status' => SellerStatus::Active]);
    $quiet = Seller::factory()->create(['status' => SellerStatus::Active]);
    $popular = Seller::factory()->create(['status' => SellerStatus::Active]);

    Product::factory()->count(2)->create(['seller_id' => $quiet->id]);
    Product::factory()->create(['seller_id' => $popular->id]);
    Product::factory()->create(['seller_id' => $popular->id, 'status' => ProductStatus::Draft]);
    $popular->followers()->attach(User::factory()->count(2)->create());

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellers.data.0.id', $popular->id)
            ->where('sellers.data.0.products_count', 1)
            ->where('sellers.data.0.followers_count', 2)
            ->where('sellers.data.1.id', $quiet->id)
            ->where('sellers.data.2.id', $empty->id)
            ->where('sellers.data.2.products_count', 0)
            ->missing('sellers.data.0.bank_account'));
});

it('shares category cards with active product counts and a cover image', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $older = Product::factory()->create(['category_id' => $category->id, 'created_at' => now()->subDay()]);
    ProductImage::factory()->create(['product_id' => $older->id, 'path' => 'products/older.jpg', 'is_primary' => true]);
    $newer = Product::factory()->create(['category_id' => $category->id]);
    ProductImage::factory()->create(['product_id' => $newer->id, 'path' => 'products/newer.jpg', 'is_primary' => true]);
    Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Draft]);

    // Without reviews the newest active product provides the cover.
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.data.0.id', $category->id)
            ->where('categories.data.0.products_count', 2)
            ->where('categories.data.0.image_url', fn (string $url) => str_ends_with($url, 'products/newer.jpg')));
});
