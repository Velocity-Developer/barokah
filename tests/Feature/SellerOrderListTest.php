<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;

function orderSeller(): User
{
    $owner = User::factory()->create(['is_active_as_seller' => true]);
    Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);

    return $owner->refresh();
}

function orderWithItem(int $sellerId, OrderStatus $status, array $overrides = [], array $itemOverrides = []): Order
{
    $order = Order::factory()->create(array_merge(['status' => $status], $overrides));
    $product = Product::factory()->create(['seller_id' => $sellerId]);

    OrderItem::factory()->create(array_merge([
        'order_id' => $order->id,
        'seller_id' => $sellerId,
        'product_id' => $product->id,
        'subtotal' => 50,
    ], $itemOverrides));

    return $order;
}

it('counts order statuses and filters the list by tab', function () {
    $owner = orderSeller();
    $sellerId = $owner->seller->id;

    orderWithItem($sellerId, OrderStatus::Completed);
    orderWithItem($sellerId, OrderStatus::PendingPayment);
    $stranger = Order::factory()->create(['status' => OrderStatus::Completed]);
    OrderItem::factory()->create(['order_id' => $stranger->id, 'seller_id' => Seller::factory()->create()->id]);

    $get = fn (string $query) => $this->actingAs($owner)->getJson('/api/v1/seller/orders?'.$query)->assertOk();

    // The other store's order is not counted anywhere.
    $get('')
        ->assertJsonPath('status_counts_total', 2)
        ->assertJsonPath('status_counts.completed', 1)
        ->assertJsonPath('status_counts.pending_payment', 1);

    $get('status=pending_payment')->assertJsonCount(1, 'data');
});

it('searches by order number, customer and product name', function () {
    $owner = orderSeller();
    $sellerId = $owner->seller->id;

    $found = orderWithItem($sellerId, OrderStatus::Completed, ['customer_name' => 'Farah Aziz'], ['product_name_snapshot' => 'Keripik Pedas']);
    orderWithItem($sellerId, OrderStatus::Completed, ['customer_name' => 'Other Buyer'], ['product_name_snapshot' => 'Hijab Satin']);

    $get = fn (string $term) => $this->actingAs($owner)->getJson('/api/v1/seller/orders?search='.urlencode($term))->assertOk();

    $get('farah')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $found->order_number);
    $get('keripik pedas')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $found->order_number);
    $get($found->order_number)->assertJsonCount(1, 'data');
});

it('filters by how far the handover has got', function () {
    $owner = orderSeller();
    $sellerId = $owner->seller->id;

    $untouched = orderWithItem($sellerId, OrderStatus::Paid);
    $packed = orderWithItem($sellerId, OrderStatus::Paid);
    $packed->sellerTrackings()->create(['seller_id' => $sellerId, 'tracking_status' => 'packed']);

    $get = fn (string $value) => $this->actingAs($owner)->getJson('/api/v1/seller/orders?fulfilment='.$value)->assertOk();

    $get('not_started')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $untouched->order_number);
    $get('packed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_number', $packed->order_number);
    $get('delivered')->assertJsonCount(0, 'data');
});

it('totals only this store items in a shared order', function () {
    $owner = orderSeller();
    $sellerId = $owner->seller->id;

    $order = orderWithItem($sellerId, OrderStatus::Completed, [], ['subtotal' => 80, 'product_name_snapshot' => 'Keripik Manis']);
    OrderItem::factory()->create(['order_id' => $order->id, 'seller_id' => Seller::factory()->create()->id, 'subtotal' => 500]);

    $this->actingAs($owner)->getJson('/api/v1/seller/orders')
        ->assertOk()
        ->assertJsonPath('data.0.subtotal', 80)
        ->assertJsonPath('data.0.items_count', 1)
        ->assertJsonPath('data.0.first_item', 'Keripik Manis');
});

it('saves courier details and records when the parcel was handed over', function () {
    $owner = orderSeller();
    $sellerId = $owner->seller->id;
    $order = orderWithItem($sellerId, OrderStatus::Paid);
    $order->payment()->create([
        'payment_method' => 'bank_transfer',
        'payment_gateway' => 'manual',
        'status' => PaymentStatus::Paid,
        'amount' => 50,
        'paid_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post('/api/v1/seller/orders/'.$order->order_number.'/tracking', [
            'tracking_status' => 'shipped',
            'courier' => 'J&T Express',
            'waybill_number' => '630123456789',
            'tracking_url' => 'https://jtexpress.my/track/630123456789',
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $tracking = $order->sellerTrackings()->where('seller_id', $sellerId)->firstOrFail();

    // "shipped" is stored as the courier handover.
    expect($tracking->tracking_status)->toBe('picked_up')
        ->and($tracking->courier)->toBe('J&T Express')
        ->and($tracking->waybill_number)->toBe('630123456789')
        ->and($tracking->picked_up_at)->not->toBeNull();
});

it('refuses tracking while the order is unpaid', function () {
    $owner = orderSeller();
    $order = orderWithItem($owner->seller->id, OrderStatus::PendingPayment);

    $this->actingAs($owner)
        ->post('/api/v1/seller/orders/'.$order->order_number.'/tracking', ['tracking_status' => 'packed'], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect($order->sellerTrackings()->count())->toBe(0);
});
