<?php

use App\Enums\SellerStatus;
use App\Models\Coupon;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function couponSeller(): User
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);

    return $owner->refresh();
}

function storeCoupon(int $sellerId, array $overrides = []): Coupon
{
    return Coupon::query()->create(array_merge([
        'code' => 'CODE'.fake()->unique()->numberBetween(100, 999),
        'name' => 'Store coupon',
        'owner_type' => 'seller',
        'seller_id' => $sellerId,
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'minimum_spend' => 0,
        'usage_count' => 0,
        'status' => true,
        'allow_flash_sale' => false,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(7),
    ], $overrides));
}

it('lists only this store coupons, never the marketplace ones', function () {
    $owner = couponSeller();
    $sellerId = $owner->seller->id;

    $mine = storeCoupon($sellerId);
    storeCoupon(Seller::factory()->create()->id);
    storeCoupon($sellerId, ['owner_type' => 'global', 'seller_id' => null]);

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.coupons.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Coupons/Index')
            ->has('coupons.data', 1)
            ->where('coupons.data.0.id', $mine->id)
            ->where('statusCounts.active', 1));
});

it('filters coupons by status and code', function () {
    $owner = couponSeller();
    $sellerId = $owner->seller->id;

    $active = storeCoupon($sellerId, ['code' => 'RAYA10']);
    storeCoupon($sellerId, ['code' => 'NANTI20', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(5)]);

    $get = fn (array $query) => $this->withoutVite()->actingAs($owner)->get(route('seller.coupons.index', $query))->assertOk();

    $get(['status' => 'active'])->assertInertia(fn (Assert $page) => $page->has('coupons.data', 1)->where('coupons.data.0.id', $active->id));
    $get(['status' => 'scheduled'])->assertInertia(fn (Assert $page) => $page->has('coupons.data', 1)->where('coupons.data.0.code', 'NANTI20'));
    $get(['search' => 'raya'])->assertInertia(fn (Assert $page) => $page->has('coupons.data', 1)->where('coupons.data.0.id', $active->id));
});

it('creates a coupon that belongs to the seller store', function () {
    $owner = couponSeller();

    $this->actingAs($owner)
        ->postJson('/api/v1/seller/coupons', [
            'code' => 'TOKOBARU',
            'name' => 'Store coupon',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_spend' => 0,
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addDays(7)->toIso8601String(),
            'status' => true,
        ])
        ->assertSuccessful();

    $coupon = Coupon::query()->where('code', 'TOKOBARU')->firstOrFail();

    expect($coupon->owner_type)->toBe('seller')
        ->and($coupon->seller_id)->toBe($owner->seller->id);
});

it('keeps one store out of another store coupons', function () {
    $owner = couponSeller();
    $stranger = couponSeller();
    $coupon = storeCoupon($owner->seller->id);

    $this->withoutVite()->actingAs($stranger)->get(route('seller.coupons.edit', $coupon))->assertForbidden();
    $this->actingAs($stranger)->putJson('/api/v1/seller/coupons/'.$coupon->id, ['name' => 'Taken over'])->assertForbidden();
    $this->actingAs($stranger)->deleteJson('/api/v1/seller/coupons/'.$coupon->id)->assertForbidden();
});
