<?php

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use Inertia\Testing\AssertableInertia as Assert;

it('ranks best seller products by sold quantity descending', function () {
    $seller = Seller::factory()->create();
    $category = Category::factory()->create();

    $lowSold = Product::factory()->for($seller)->for($category)->create([
        'name' => 'Low Sold Product',
        'status' => ProductStatus::Active,
    ]);
    $midSold = Product::factory()->for($seller)->for($category)->create([
        'name' => 'Mid Sold Product',
        'status' => ProductStatus::Active,
    ]);
    $highSold = Product::factory()->for($seller)->for($category)->create([
        'name' => 'High Sold Product',
        'status' => ProductStatus::Active,
    ]);

    $orderHigh = Order::factory()->create(['status' => OrderStatus::Completed]);
    OrderItem::factory()->for($orderHigh)->for($seller)->create([
        'product_id' => $highSold->id,
        'quantity' => 10,
        'price_snapshot' => $highSold->price,
        'subtotal' => $highSold->price * 10,
    ]);

    $orderMid = Order::factory()->create(['status' => OrderStatus::Paid]);
    OrderItem::factory()->for($orderMid)->for($seller)->create([
        'product_id' => $midSold->id,
        'quantity' => 5,
        'price_snapshot' => $midSold->price,
        'subtotal' => $midSold->price * 5,
    ]);

    $orderLow = Order::factory()->create(['status' => OrderStatus::Shipped]);
    OrderItem::factory()->for($orderLow)->for($seller)->create([
        'product_id' => $lowSold->id,
        'quantity' => 2,
        'price_snapshot' => $lowSold->price,
        'subtotal' => $lowSold->price * 2,
    ]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('bestSellerProducts.data', 3)
            ->where('bestSellerProducts.data.0.id', $highSold->id)
            ->where('bestSellerProducts.data.1.id', $midSold->id)
            ->where('bestSellerProducts.data.2.id', $lowSold->id)
        );
});

it('excludes orders with statuses pending_payment expired and cancelled from sold count', function () {
    $seller = Seller::factory()->create();
    $category = Category::factory()->create();

    $product = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Active,
    ]);
    $validProduct = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Active,
    ]);

    $pendingOrder = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
    OrderItem::factory()->for($pendingOrder)->for($seller)->create([
        'product_id' => $product->id,
        'quantity' => 7,
        'price_snapshot' => $product->price,
        'subtotal' => $product->price * 7,
    ]);

    $expiredOrder = Order::factory()->create(['status' => OrderStatus::Expired]);
    OrderItem::factory()->for($expiredOrder)->for($seller)->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'price_snapshot' => $product->price,
        'subtotal' => $product->price * 3,
    ]);

    $cancelledOrder = Order::factory()->create(['status' => OrderStatus::Cancelled]);
    OrderItem::factory()->for($cancelledOrder)->for($seller)->create([
        'product_id' => $product->id,
        'quantity' => 5,
        'price_snapshot' => $product->price,
        'subtotal' => $product->price * 5,
    ]);

    $validOrder = Order::factory()->create(['status' => OrderStatus::Processing]);
    OrderItem::factory()->for($validOrder)->for($seller)->create([
        'product_id' => $validProduct->id,
        'quantity' => 4,
        'price_snapshot' => $validProduct->price,
        'subtotal' => $validProduct->price * 4,
    ]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('bestSellerProducts.data', 1)
            ->where('bestSellerProducts.data.0.id', $validProduct->id)
            ->where('bestSellerProducts.data.0.sold_count', 4)
        );
});

it('excludes products without any sold order items', function () {
    $seller = Seller::factory()->create();
    $category = Category::factory()->create();

    $noSales = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Active,
    ]);
    $withSales = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Active,
    ]);

    $order = Order::factory()->create(['status' => OrderStatus::Completed]);
    OrderItem::factory()->for($order)->for($seller)->create([
        'product_id' => $withSales->id,
        'quantity' => 3,
        'price_snapshot' => $withSales->price,
        'subtotal' => $withSales->price * 3,
    ]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('bestSellerProducts.data', 1)
            ->where('bestSellerProducts.data.0.id', $withSales->id)
        );
});

it('excludes inactive and draft products even when they have sales', function () {
    $seller = Seller::factory()->create();
    $category = Category::factory()->create();

    $draft = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Draft,
    ]);
    $inactive = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Inactive,
    ]);
    $active = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Active,
    ]);

    foreach ([$draft, $inactive, $active] as $product) {
        $order = Order::factory()->create(['status' => OrderStatus::Completed]);
        OrderItem::factory()->for($order)->for($seller)->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price_snapshot' => $product->price,
            'subtotal' => $product->price * 2,
        ]);
    }

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('bestSellerProducts.data', 1)
            ->where('bestSellerProducts.data.0.id', $active->id)
        );
});

it('includes sold_count field with aggregated quantity', function () {
    $seller = Seller::factory()->create();
    $category = Category::factory()->create();

    $product = Product::factory()->for($seller)->for($category)->create([
        'status' => ProductStatus::Active,
    ]);

    $order1 = Order::factory()->create(['status' => OrderStatus::Paid]);
    OrderItem::factory()->for($order1)->for($seller)->create([
        'product_id' => $product->id,
        'quantity' => 5,
        'price_snapshot' => $product->price,
        'subtotal' => $product->price * 5,
    ]);

    $order2 = Order::factory()->create(['status' => OrderStatus::Completed]);
    OrderItem::factory()->for($order2)->for($seller)->create([
        'product_id' => $product->id,
        'quantity' => 3,
        'price_snapshot' => $product->price,
        'subtotal' => $product->price * 3,
    ]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('bestSellerProducts.data', 1)
            ->where('bestSellerProducts.data.0.id', $product->id)
            ->where('bestSellerProducts.data.0.sold_count', 8)
        );
});
