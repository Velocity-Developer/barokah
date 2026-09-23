<?php

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function flashSaleSeller(): User
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);

    return $owner->refresh();
}

function saleFor(int $sellerId, array $overrides = []): FlashSale
{
    $product = Product::factory()->create(['seller_id' => $sellerId, 'status' => ProductStatus::Active, 'price' => 100, 'stock' => 50]);

    return FlashSale::factory()->create(array_merge([
        'product_id' => $product->id,
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'price' => 80,
        'quantity' => 10,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
    ], $overrides));
}

it('lists only this store flash sales, with status counts', function () {
    $owner = flashSaleSeller();
    $sellerId = $owner->seller->id;

    saleFor($sellerId);
    saleFor($sellerId, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);
    saleFor(Seller::factory()->create()->id);

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.flash-sales.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Seller/FlashSales/Index')
            ->has('flashSales.data', 2)
            ->where('statusCounts.active', 1)
            ->where('statusCounts.scheduled', 1)
            ->where('statusCounts.ended', 0));
});

it('filters the list by status and product name', function () {
    $owner = flashSaleSeller();
    $sellerId = $owner->seller->id;

    $running = saleFor($sellerId);
    $running->product->update(['name' => 'Keripik Pedas']);
    saleFor($sellerId, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.flash-sales.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('flashSales.data', 1)->where('flashSales.data.0.id', $running->id));

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.flash-sales.index', ['search' => 'pedas']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('flashSales.data', 1)->where('flashSales.data.0.id', $running->id));
});

it('lets the owner edit one flash sale by id and keeps others out', function () {
    $owner = flashSaleSeller();
    $sale = saleFor($owner->seller->id);
    $stranger = flashSaleSeller();

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.flash-sales.edit', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('flashSale.id', $sale->id));

    $this->withoutVite()->actingAs($stranger)
        ->get(route('seller.flash-sales.edit', $sale))
        ->assertNotFound();

    $this->actingAs($owner)
        ->putJson('/api/v1/seller/flash-sales/'.$sale->id, [
            'discount_type' => 'percentage',
            'discount_value' => 30,
            'quantity' => 15,
            'starts_at' => now()->subHour()->toIso8601String(),
            'ends_at' => now()->addDays(2)->toIso8601String(),
        ])
        ->assertOk();

    expect((int) $sale->fresh()->quantity)->toBe(15)
        ->and((float) $sale->fresh()->price)->toBe(70.0);

    $this->actingAs($stranger)
        ->putJson('/api/v1/seller/flash-sales/'.$sale->id, ['quantity' => 99])
        ->assertForbidden();
});

it('ends a running sale and deletes one that has not started', function () {
    $owner = flashSaleSeller();
    $running = saleFor($owner->seller->id);
    $scheduled = saleFor($owner->seller->id, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);

    $this->actingAs($owner)->deleteJson('/api/v1/seller/flash-sales/'.$running->id)->assertNoContent();
    $this->actingAs($owner)->deleteJson('/api/v1/seller/flash-sales/'.$scheduled->id)->assertNoContent();

    expect($running->fresh()->ends_at->lte(now()))->toBeTrue()
        ->and(FlashSale::query()->whereKey($scheduled->id)->exists())->toBeFalse();
});
