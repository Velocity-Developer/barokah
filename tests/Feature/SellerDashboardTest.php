<?php

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function storeOwner(array $attributes = []): User
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(array_merge(['user_id' => $owner->id, 'status' => SellerStatus::Active], $attributes));

    return $owner->refresh();
}

it('counts only this store items, not the rest of a shared order', function () {
    $owner = storeOwner();
    $mine = Product::factory()->create(['seller_id' => $owner->seller->id, 'status' => ProductStatus::Active]);
    $other = Product::factory()->create(['status' => ProductStatus::Active]);

    $order = Order::factory()->create(['status' => OrderStatus::Completed]);
    OrderItem::factory()->create(['order_id' => $order->id, 'seller_id' => $mine->seller_id, 'product_id' => $mine->id, 'quantity' => 2, 'subtotal' => 100]);
    OrderItem::factory()->create(['order_id' => $order->id, 'seller_id' => $other->seller_id, 'product_id' => $other->id, 'quantity' => 5, 'subtotal' => 900]);

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Dashboard')
            ->where('stats.revenue_formatted', 'RM 100.00')
            ->where('stats.items_sold', 2)
            ->where('stats.orders_count', 1)
            ->has('recent_orders', 1)
            ->where('recent_orders.0.store_total_formatted', 'RM 100.00')
            ->where('recent_orders.0.items_count', 1));
});

it('leaves unpaid orders out of revenue but still counts them as orders', function () {
    $owner = storeOwner();
    $product = Product::factory()->create(['seller_id' => $owner->seller->id]);

    $pending = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
    OrderItem::factory()->create(['order_id' => $pending->id, 'seller_id' => $product->seller_id, 'product_id' => $product->id, 'quantity' => 1, 'subtotal' => 70]);

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.revenue_formatted', 'RM 0.00')
            ->where('stats.items_sold', 0)
            ->where('stats.orders_count', 1)
            ->where('attention.awaiting_payment', 1));
});

it('lists the stock and products that need attention', function () {
    $owner = storeOwner();
    $sellerId = $owner->seller->id;
    Product::factory()->create(['seller_id' => $sellerId, 'status' => ProductStatus::Active, 'stock' => 2, 'name' => 'Keripik Tipis']);
    Product::factory()->create(['seller_id' => $sellerId, 'status' => ProductStatus::Draft]);

    $this->withoutVite()->actingAs($owner)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.product_count', 2)
            ->where('stats.active_product_count', 1)
            ->where('attention.inactive_products', 1)
            ->has('attention.low_stock_products', 1)
            ->where('attention.low_stock_products.0.name', 'Keripik Tipis'));
});
