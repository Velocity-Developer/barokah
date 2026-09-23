<?php

use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Models\User;

/**
 * Account settings live in the dashboard: admins and sellers edit their own
 * details there (it used to be admin-only, so sellers got a 403), while
 * buyers use the storefront profile page.
 */
it('opens account settings for sellers and admins', function () {
    $seller = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $seller->id, 'status' => SellerStatus::Active]);

    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    foreach ([$seller->refresh(), $admin] as $user) {
        $this->withoutVite()->actingAs($user)->get('/settings/profile')->assertOk();
        $this->withoutVite()->actingAs($user)->get('/settings/appearance')->assertOk();
    }
});

it('sends a buyer to their own profile page instead', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)->get('/settings/profile')->assertRedirect(route('profile.show'));
    $this->actingAs($buyer)->get('/settings/appearance')->assertRedirect(route('profile.show'));
    $this->actingAs($buyer)->patch('/settings/profile', ['name' => 'Nope', 'email' => 'nope@example.com'])
        ->assertRedirect(route('profile.show'));

    expect($buyer->fresh()->name)->not->toBe('Nope');
});

it('still keeps guests out', function () {
    $this->get('/settings/profile')->assertRedirect('/login');
});

it('lets a seller save their own name and email', function () {
    $seller = User::factory()->create(['name' => 'Siti', 'is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $seller->id, 'status' => SellerStatus::Active]);

    $this->actingAs($seller->refresh())
        ->patch('/settings/profile', ['name' => 'Siti Modest', 'email' => 'siti@example.com'])
        ->assertRedirect();

    expect($seller->fresh()->name)->toBe('Siti Modest')
        ->and($seller->fresh()->email)->toBe('siti@example.com');
});
