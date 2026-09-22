<?php

use App\Models\FlashSale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function flashAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function makeSale(Product $product, array $attributes): FlashSale
{
    return FlashSale::query()->create(array_merge([
        'product_id' => $product->id,
        'price' => 8,
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'quantity' => 10,
        'quantity_sold' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ], $attributes));
}

it('lists flash sales with a computed status, counts and filters', function () {
    $product = Product::factory()->create(['name' => 'Banana Chips', 'price' => 10]);
    makeSale($product, []);
    makeSale(Product::factory()->create(), ['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(3)]);
    makeSale(Product::factory()->create(), ['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
    makeSale(Product::factory()->create(), ['quantity_sold' => 10]);

    $admin = flashAdmin();

    $this->withoutVite()->actingAs($admin)
        ->get(route('admin.flash-sales.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/FlashSales/Index')
            ->has('flashSales.data', 4)
            ->where('statusCounts.active', 1)
            ->where('statusCounts.scheduled', 1)
            ->where('statusCounts.ended', 1)
            ->where('statusCounts.sold_out', 1));

    $this->withoutVite()->actingAs($admin)
        ->get(route('admin.flash-sales.index', ['status' => 'active', 'search' => 'banana']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('flashSales.data', 1)
            ->where('flashSales.data.0.status', 'active')
            ->where('flashSales.data.0.product.name', 'Banana Chips')
            ->where('flashSales.data.0.discount_label', '20% off'));
});

it('stores the exact moment sent with a time zone offset', function () {
    $product = Product::factory()->create(['price' => 50, 'stock' => 20]);

    $this->actingAs(flashAdmin())
        ->postJson("/api/v1/admin/products/{$product->id}/flash-sale", [
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'quantity' => 5,
            'starts_at' => '2031-01-10T02:00:00.000Z',
            'ends_at' => '2031-01-12T02:00:00.000Z',
        ])
        ->assertCreated();

    $sale = FlashSale::query()->firstOrFail();

    expect($sale->starts_at->equalTo(Carbon::parse('2031-01-10 02:00:00', 'UTC')))->toBeTrue()
        ->and((float) $sale->price)->toBe(45.0);
});

it('passes product details to the edit page', function () {
    $product = Product::factory()->create(['price' => 30, 'stock' => 7]);
    $sale = makeSale($product, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);

    $this->withoutVite()->actingAs(flashAdmin())
        ->get(route('admin.flash-sales.edit', $sale))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/FlashSales/Edit')
            ->where('flashSale.status', 'scheduled')
            ->where('flashSale.product.price', 30)
            ->where('flashSale.product.stock', 7));
});
