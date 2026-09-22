<?php

use App\Enums\SellerStatus;
use App\Models\Coupon;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function couponAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function makeCoupon(array $attributes = []): Coupon
{
    return Coupon::query()->create(array_merge([
        'code' => 'CODE'.fake()->unique()->numberBetween(100, 999),
        'name' => 'Test coupon',
        'owner_type' => 'global',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'minimum_spend' => 0,
        'usage_count' => 0,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'status' => true,
        'allow_flash_sale' => false,
    ], $attributes));
}

it('lists coupons with computed status, counts and filters', function () {
    makeCoupon(['code' => 'RAYA10']);
    makeCoupon(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
    makeCoupon(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
    makeCoupon(['usage_limit' => 5, 'usage_count' => 5]);
    makeCoupon(['status' => false]);

    $admin = couponAdmin();

    $this->withoutVite()->actingAs($admin)
        ->get('/admin/coupons')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Coupons/Index')
            ->has('coupons.data', 5)
            ->where('statusCounts.active', 1)
            ->where('statusCounts.scheduled', 1)
            ->where('statusCounts.expired', 1)
            ->where('statusCounts.used_up', 1)
            ->where('statusCounts.disabled', 1));

    $this->withoutVite()->actingAs($admin)
        ->get('/admin/coupons?status=active&search=raya')
        ->assertInertia(fn (Assert $page) => $page
            ->has('coupons.data', 1)
            ->where('coupons.data.0.code', 'RAYA10')
            ->where('coupons.data.0.discount_label', '10% off'));
});

it('lets admins create marketplace-wide and store coupons', function () {
    $store = Seller::factory()->create(['status' => SellerStatus::Active]);
    $admin = couponAdmin();
    $payload = [
        'name' => 'Store deal',
        'discount_type' => 'fixed',
        'discount_value' => 5,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addWeek()->toIso8601String(),
        'status' => true,
    ];

    $this->actingAs($admin)->postJson('/api/v1/admin/coupons', [...$payload, 'code' => 'ALL5'])->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/admin/coupons', [...$payload, 'code' => 'SHOP5', 'owner_type' => 'seller', 'seller_id' => $store->id])->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/admin/coupons', [...$payload, 'code' => 'BAD5', 'owner_type' => 'seller'])->assertUnprocessable()->assertJsonValidationErrors('seller_id');

    expect(Coupon::query()->where('code', 'ALL5')->value('owner_type'))->toBe('global')
        ->and(Coupon::query()->where('code', 'SHOP5')->first()->only(['owner_type', 'seller_id']))->toBe(['owner_type' => 'seller', 'seller_id' => $store->id]);
});

it('refuses to delete a coupon that has been used', function () {
    $used = makeCoupon(['usage_count' => 2]);
    $unused = makeCoupon();
    $admin = couponAdmin();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/coupons/{$used->id}")->assertUnprocessable();
    $this->actingAs($admin)->deleteJson("/api/v1/admin/coupons/{$unused->id}")->assertNoContent();

    expect(Coupon::query()->whereKey($used->id)->exists())->toBeTrue()
        ->and(Coupon::query()->whereKey($unused->id)->exists())->toBeFalse();
});

it('passes coupon details and stores to the edit page', function () {
    $coupon = makeCoupon(['maximum_discount' => 20]);
    Seller::factory()->create();

    $this->withoutVite()->actingAs(couponAdmin())
        ->get("/admin/coupons/{$coupon->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Coupons/Edit')
            ->where('coupon.code', $coupon->code)
            ->where('coupon.maximum_discount', 20)
            ->has('sellers', 1));
});
