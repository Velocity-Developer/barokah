<?php

use App\Models\User;
use App\Support\PageChrome;

it('knows which pages wear the dashboard chrome', function () {
    expect(PageChrome::usesDashboard('Admin/Dashboard'))->toBeTrue()
        ->and(PageChrome::usesDashboard('Seller/Orders/Index'))->toBeTrue()
        ->and(PageChrome::usesDashboard('settings/Appearance'))->toBeTrue()
        ->and(PageChrome::usesDashboard('Home'))->toBeFalse()
        ->and(PageChrome::usesDashboard('Product/Show'))->toBeFalse()
        ->and(PageChrome::usesDashboard('Checkout/Show'))->toBeFalse()
        ->and(PageChrome::usesDashboard('Profile/Show'))->toBeFalse()
        ->and(PageChrome::usesDashboard('auth/Login'))->toBeFalse()
        ->and(PageChrome::usesDashboard(null))->toBeFalse();
});

it('keeps the storefront and the login page light even when dark is chosen', function () {
    foreach (['/', '/login'] as $path) {
        $html = $this->withUnencryptedCookie('appearance', 'dark')->get($path)->assertOk()->getContent();

        expect($html)->not->toContain('<html lang="en" class="dark"')
            ->and($html)->not->toContain('prefers-color-scheme: dark');
    }
});

it('keeps the dark choice inside the dashboard', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $html = $this->withUnencryptedCookie('appearance', 'dark')
        ->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('class="dark"');
});
