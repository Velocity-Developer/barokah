<?php

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares no dashboard abilities with guests and regular buyers', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.admin', false)
            ->where('auth.can.seller', false));

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.admin', false)
            ->where('auth.can.seller', false));
});

it('shares the admin ability with admins', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.admin', true)
            ->where('auth.can.seller', false));
});

it('shares the seller ability only with active sellers', function () {
    $seller = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $seller->id, 'status' => SellerStatus::Active]);

    $pending = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $pending->id, 'status' => SellerStatus::Pending]);

    $this->actingAs($seller)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can.admin', false)
            ->where('auth.can.seller', true));

    $this->actingAs($pending)
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can.seller', false));
});
