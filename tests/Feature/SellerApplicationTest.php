<?php

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function makeAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function pendingApplication(): Seller
{
    $user = User::factory()->create(['is_active_as_seller' => true]);

    return Seller::factory()->create(['user_id' => $user->id, 'status' => SellerStatus::Pending]);
}

it('sends guests to login from the seller center', function () {
    $this->get(route('seller-center'))->assertRedirect(route('login'));
});

it('routes the seller center by role', function () {
    $this->actingAs(makeAdmin())->get(route('seller-center'))->assertRedirect(route('admin.dashboard'));

    $seller = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $seller->id, 'status' => SellerStatus::Active]);
    $this->actingAs($seller)->get(route('seller-center'))->assertRedirect(route('seller.dashboard'));

    $this->actingAs(User::factory()->create())
        ->get(route('seller-center'))
        ->assertRedirect(route('profile.show', ['tab' => 'seller']));

    $this->actingAs(pendingApplication()->user)
        ->get(route('seller-center'))
        ->assertRedirect(route('profile.show', ['tab' => 'seller']));
});

it('lets a buyer apply as seller from the profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('my.profile.seller-application.store'), [
            'store_name' => 'Toko Baru',
            'description' => 'Jual keripik.',
        ])
        ->assertRedirect(route('profile.show', ['tab' => 'seller']))
        ->assertSessionHasNoErrors();

    $seller = $user->refresh()->seller;
    expect($seller->status)->toBe(SellerStatus::Pending)
        ->and($user->isSeller())->toBeFalse();

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellerApplication.store_name', 'Toko Baru')
            ->where('sellerApplication.status', 'pending'));
});

it('does not create a second application', function () {
    $seller = pendingApplication();

    $this->actingAs($seller->user)->post(route('my.profile.seller-application.store'), [
        'store_name' => 'Toko Lain',
    ]);

    expect(Seller::query()->where('user_id', $seller->user_id)->count())->toBe(1);
});

it('validates the store name when applying', function () {
    $existing = Seller::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('my.profile.seller-application.store'), ['store_name' => $existing->store_name])
        ->assertSessionHasErrors('store_name');
});

it('lists pending applications for admins only', function () {
    $application = pendingApplication();
    Seller::factory()->create(['status' => SellerStatus::Active]);

    $this->withoutVite()
        ->actingAs(makeAdmin())
        ->get(route('admin.seller-approvals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sellers/Approvals')
            ->has('applications', 1)
            ->where('applications.0.id', $application->id));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.seller-approvals.index'))
        ->assertForbidden();
});

it('approves a pending application and grants seller access', function () {
    $application = pendingApplication();

    $this->actingAs(makeAdmin())
        ->post(route('admin.sellers.approve', $application))
        ->assertRedirect();

    expect($application->refresh()->status)->toBe(SellerStatus::Active)
        ->and($application->user->refresh()->isSeller())->toBeTrue();

    $this->actingAs($application->user)->get(route('seller-center'))->assertRedirect(route('seller.dashboard'));
});

it('rejects a pending application so the user can apply again', function () {
    $application = pendingApplication();
    $user = $application->user;

    $this->actingAs(makeAdmin())->post(route('admin.sellers.reject', $application))->assertRedirect();

    expect(Seller::query()->whereKey($application->id)->exists())->toBeFalse()
        ->and($user->refresh()->is_active_as_seller)->toBeFalse();

    $this->actingAs($user)
        ->post(route('my.profile.seller-application.store'), ['store_name' => 'Coba Lagi'])
        ->assertSessionHasNoErrors();
    expect($user->refresh()->seller->status)->toBe(SellerStatus::Pending);
});

it('only decides pending applications and only as admin', function () {
    $active = Seller::factory()->create(['status' => SellerStatus::Active]);
    $admin = makeAdmin();

    $this->actingAs($admin)->post(route('admin.sellers.approve', $active))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.sellers.reject', $active))->assertNotFound();
    expect($active->refresh()->status)->toBe(SellerStatus::Active);

    $application = pendingApplication();
    $this->actingAs(User::factory()->create())->post(route('admin.sellers.approve', $application))->assertForbidden();
    expect($application->refresh()->status)->toBe(SellerStatus::Pending);
});
