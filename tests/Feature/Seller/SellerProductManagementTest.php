<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;

function sellerProductOwner(): User
{
    $user = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $user->id]);

    return $user->refresh();
}

test('seller product page requires seller auth', function () {
    $this->get(route('seller.products.index'))->assertRedirect('/login');

    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get(route('seller.products.index'))->assertForbidden();
});

test('seller can view product management page', function () {
    $seller = sellerProductOwner();

    $this->withoutVite()->actingAs($seller)->get(route('seller.products.index'))->assertOk();
    $this->withoutVite()->actingAs($seller)->get(route('seller.products.create'))->assertOk();
});

test('seller product API only returns own products', function () {
    $seller = sellerProductOwner();
    $other = sellerProductOwner();
    Product::factory()->create(['seller_id' => $seller->seller->id]);
    Product::factory()->create(['seller_id' => $other->seller->id]);

    $this->actingAs($seller)->getJson('/api/v1/seller/products')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('seller creates product for own store', function () {
    $seller = sellerProductOwner();
    $category = Category::factory()->create();

    $this->actingAs($seller)->postJson('/api/v1/seller/products', [
        'category_id' => $category->id,
        'name' => 'Seller Product',
        'description' => 'Product description',
        'price' => 25.5,
        'stock' => 8,
        'weight_grams' => 500,
        'status' => 'draft',
    ])->assertCreated();

    expect(Product::query()->where('seller_id', $seller->seller->id)->where('name', 'Seller Product')->exists())->toBeTrue();
});

test('seller cannot update another sellers product', function () {
    $seller = sellerProductOwner();
    $other = sellerProductOwner();
    $product = Product::factory()->create(['seller_id' => $other->seller->id]);

    $this->actingAs($seller)->putJson("/api/v1/seller/products/{$product->id}", [
        'name' => 'Hijacked product',
    ])->assertForbidden();
});

test('seller creates fixed flash sale for own product', function () {
    $seller = sellerProductOwner();
    $product = Product::factory()->create(['seller_id' => $seller->seller->id, 'price' => 100, 'stock' => 10]);

    $this->actingAs($seller)->postJson("/api/v1/seller/products/{$product->id}/flash-sale", [
        'discount_type' => 'fixed',
        'discount_value' => 75,
        'quantity' => 5,
        'starts_at' => now()->subMinute()->toISOString(),
        'ends_at' => now()->addDay()->toISOString(),
    ])->assertOk()->assertJsonPath('data.price', '75.00');

    expect($product->refresh()->activeFlashSale()->price)->toBe('75.00');
});

test('seller cannot create flash sale for another sellers product', function () {
    $seller = sellerProductOwner();
    $other = sellerProductOwner();
    $product = Product::factory()->create(['seller_id' => $other->seller->id]);

    $this->actingAs($seller)->postJson("/api/v1/seller/products/{$product->id}/flash-sale", [
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'quantity' => 1,
        'starts_at' => now()->toISOString(),
        'ends_at' => now()->addDay()->toISOString(),
    ])->assertForbidden();
});
