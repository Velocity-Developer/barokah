<?php

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;

function productAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('searches, filters, sorts and counts products', function () {
    $store = Seller::factory()->create(['status' => SellerStatus::Active, 'store_name' => 'Kerepek Mak Cik']);
    $snacks = Category::factory()->create(['name' => 'Snacks']);
    Product::factory()->create(['name' => 'Banana Chips', 'seller_id' => $store->id, 'category_id' => $snacks->id, 'price' => 12, 'stock' => 0]);
    Product::factory()->create(['name' => 'Tapioca Chips', 'price' => 30, 'stock' => 3]);
    Product::factory()->create(['name' => 'Silk Hijab', 'price' => 20, 'stock' => 40, 'status' => ProductStatus::Draft]);

    $admin = productAdmin();
    $get = fn (string $query) => $this->actingAs($admin)->getJson('/api/v1/admin/products?'.$query)->assertOk();
    $names = fn (string $query) => collect($get($query)->json('data'))->pluck('name')->all();

    expect($names('search=chips&sort=name_asc'))->toBe(['Banana Chips', 'Tapioca Chips'])
        ->and($names('search=mak+cik'))->toBe(['Banana Chips'])
        ->and($names('search=snacks'))->toBe(['Banana Chips'])
        ->and($names('category_id='.$snacks->id))->toBe(['Banana Chips'])
        ->and($names('stock=out'))->toBe(['Banana Chips'])
        ->and($names('stock=low'))->toBe(['Tapioca Chips'])
        ->and($names('status=draft'))->toBe(['Silk Hijab'])
        ->and($names('sort=price_desc'))->toBe(['Tapioca Chips', 'Silk Hijab', 'Banana Chips']);

    $get('status=draft&search=chips')
        ->assertJsonPath('status_counts.active', 2)
        ->assertJsonPath('status_counts_total', 2)
        ->assertJsonCount(0, 'data');
});

it('paginates the product list', function () {
    Product::factory()->count(20)->create();

    $this->actingAs(productAdmin())
        ->getJson('/api/v1/admin/products?per_page=15&page=2')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 20);
});

it('rejects invalid product filters', function () {
    $this->actingAs(productAdmin())
        ->getJson('/api/v1/admin/products?stock=some&sort=random&per_page=7')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['stock', 'sort', 'per_page']);
});
