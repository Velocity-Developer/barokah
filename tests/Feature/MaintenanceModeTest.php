<?php

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Models\User;
use App\Services\SettingsService;
use Inertia\Testing\AssertableInertia as Assert;

function maintenanceOn(): void
{
    app(SettingsService::class)->set('general.maintenance_mode', true);
}

function maintenanceAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function maintenanceSeller(): User
{
    $user = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $user->id, 'status' => SellerStatus::Active]);

    return $user;
}

it('leaves the storefront open while maintenance is off', function () {
    $this->get(route('home'))->assertOk();
});

it('shows shoppers the maintenance page', function () {
    maintenanceOn();

    $this->get(route('home'))
        ->assertStatus(503)
        ->assertHeader('Retry-After')
        ->assertSee('under maintenance');
    $this->actingAs(User::factory()->create())->get(route('products.index'))->assertStatus(503);
    $this->postJson('/api/v1/orders', [])->assertStatus(503)->assertJsonStructure(['message']);
    $this->postJson('/api/v1/coupons/validate', [])->assertStatus(503);
});

it('sends an Inertia visit to a full page load', function () {
    maintenanceOn();

    $this->get(route('home'), ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('home'));
});

it('keeps the login page and payment callbacks reachable', function () {
    maintenanceOn();

    $this->get(route('login'))->assertOk();
    $this->get(route('robots'))->assertOk()->assertSee('Disallow: /');
    $this->getJson('/api/v1/settings/public')->assertOk();
    expect($this->postJson('/api/v1/payments/callback')->status())->not->toBe(503);
});

it('lets admins use the whole site and turn maintenance off', function () {
    maintenanceOn();
    $admin = maintenanceAdmin();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('maintenance_mode', true));
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    $this->actingAs($admin)
        ->putJson('/api/v1/admin/settings', ['settings' => [['key' => 'general.maintenance_mode', 'value' => false]]])
        ->assertOk();

    $this->get(route('home'))->assertOk();
});

it('lets sellers into their dashboard but not the storefront', function () {
    maintenanceOn();
    $seller = maintenanceSeller();

    $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
    $this->actingAs($seller)->getJson('/api/v1/seller/orders')->assertOk();
    $this->actingAs($seller)->get(route('home'))->assertStatus(503);
});
