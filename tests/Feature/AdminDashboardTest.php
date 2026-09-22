<?php

use App\Enums\SellerStatus;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the admin dashboard overview', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    Seller::factory()->create(['status' => SellerStatus::Pending]);
    Product::factory()->create(['stock' => 0, 'name' => 'Sold Out Chips']);
    Product::factory()->create(['stock' => 50]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('stats.active_product_count', 2)
            ->where('attention.seller_applications', 1)
            ->where('attention.low_stock_products.0.name', 'Sold Out Chips')
            ->has('revenue_chart', 14)
            ->has('revenue_chart_total_formatted')
            ->has('recent_orders', 0)
            ->where('auth.pending_seller_approvals', 1));
});

it('does not share pending approvals with non admins', function () {
    Seller::factory()->create(['status' => SellerStatus::Pending]);

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.pending_seller_approvals', 0));
});
