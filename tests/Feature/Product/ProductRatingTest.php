<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSellerTracking;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('buyer can rate product after seller tracking is delivered', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->for($user)->create();
    $item = OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'seller_id' => $product->seller_id,
    ]);

    OrderSellerTracking::query()->create([
        'order_id' => $order->id,
        'seller_id' => $product->seller_id,
        'tracking_status' => 'delivered',
        'delivered_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/order-items/{$item->id}/review", [
        'rating' => 5,
        'review' => 'Great product.',
    ]);

    $response->assertCreated()->assertJsonPath('data.rating', 5);
    $this->assertDatabaseHas('product_reviews', [
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'user_id' => $user->id,
        'rating' => 5,
    ]);
});

test('buyer cannot rate product before delivery or rate it twice', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->for($user)->create();
    $item = OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'seller_id' => $product->seller_id,
    ]);

    $response = $this->actingAs($user)->postJson("/api/v1/order-items/{$item->id}/review", [
        'rating' => 4,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('order_item');

    OrderSellerTracking::query()->create([
        'order_id' => $order->id,
        'seller_id' => $product->seller_id,
        'tracking_status' => 'delivered',
        'delivered_at' => now(),
    ]);

    $this->actingAs($user)->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 4])->assertCreated();
    $this->actingAs($user)->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 3])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('order_item');
});
