<?php

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function productSeller(): User
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);

    return $owner->refresh();
}

it('counts statuses and never shows another store products', function () {
    $owner = productSeller();
    $sellerId = $owner->seller->id;

    Product::factory()->create(['seller_id' => $sellerId, 'status' => ProductStatus::Active]);
    Product::factory()->create(['seller_id' => $sellerId, 'status' => ProductStatus::Draft]);
    Product::factory()->create(['status' => ProductStatus::Active]);

    $get = fn (string $query) => $this->actingAs($owner)->getJson('/api/v1/seller/products?'.$query)->assertOk();

    $get('')
        ->assertJsonPath('status_counts_total', 2)
        ->assertJsonPath('status_counts.active', 1)
        ->assertJsonPath('status_counts.draft', 1);

    $get('status=draft')->assertJsonCount(1, 'data');
});

it('searches by name and filters by category', function () {
    $owner = productSeller();
    $sellerId = $owner->seller->id;
    $snacks = Category::factory()->create();

    $keripik = Product::factory()->create(['seller_id' => $sellerId, 'name' => 'Keripik Pedas', 'category_id' => $snacks->id]);
    Product::factory()->create(['seller_id' => $sellerId, 'name' => 'Hijab Satin']);

    $get = fn (string $query) => $this->actingAs($owner)->getJson('/api/v1/seller/products?'.$query)->assertOk();

    $get('search=keripik')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $keripik->id);
    $get('category_id='.$snacks->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $keripik->id);
    $get('search=nothing-here')->assertJsonCount(0, 'data');
});

it('sorts by the lowest stock first', function () {
    $owner = productSeller();
    $sellerId = $owner->seller->id;

    Product::factory()->create(['seller_id' => $sellerId, 'stock' => 40]);
    $almostOut = Product::factory()->create(['seller_id' => $sellerId, 'stock' => 1]);

    $this->actingAs($owner)->getJson('/api/v1/seller/products?sort=stock_asc')
        ->assertOk()
        ->assertJsonPath('data.0.id', $almostOut->id);
});

it('shows the flash sale price beside the normal price', function () {
    $owner = productSeller();
    $product = Product::factory()->create(['seller_id' => $owner->seller->id, 'price' => 100]);
    FlashSale::factory()->create([
        'product_id' => $product->id,
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'price' => 80,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)->getJson('/api/v1/seller/products')
        ->assertOk()
        ->assertJsonPath('data.0.flash_sale_active', true)
        ->assertJsonPath('data.0.effective_price', '80.00')
        ->assertJsonPath('data.0.price', '100.00');
});

it('keeps flash sales off the product edit page', function () {
    $owner = productSeller();
    $product = Product::factory()->create(['seller_id' => $owner->seller->id]);

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.products.edit', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Products/Edit')
            ->missing('product.flash_sale'));
});
