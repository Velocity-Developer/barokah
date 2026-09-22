<?php

use App\Enums\OrderStatus;
use App\Enums\SellerStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;

function filterAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function makeOrder(string $number, array $attributes = [], ?Product $product = null): Order
{
    $order = Order::query()->create(array_merge([
        'order_number' => $number,
        'customer_name' => 'Customer '.$number,
        'customer_address' => 'Jalan 1',
        'customer_state' => 'Selangor',
        'customer_post_code' => '40000',
        'customer_phone' => '0120000000',
        'currency_code' => 'MYR',
        'subtotal' => 10,
        'discount_amount' => 0,
        'shipping_fee' => 0,
        'total' => 10,
        'status' => OrderStatus::PendingPayment,
        'shipping_method' => 'fixed',
    ], collect($attributes)->except('created_at')->all()));

    if (isset($attributes['created_at'])) {
        $order->forceFill(['created_at' => $attributes['created_at']])->save();
    }

    if ($product !== null) {
        $order->items()->create([
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'product_name_snapshot' => $product->name,
            'product_slug_snapshot' => $product->slug,
            'price_snapshot' => 10,
            'quantity' => 1,
            'subtotal' => 10,
            'discount_amount' => 0,
        ]);
    }

    return $order;
}

it('searches orders by number, customer and product', function () {
    $store = Seller::factory()->create(['status' => SellerStatus::Active, 'store_name' => 'Kerepek Mak Cik']);
    $chips = Product::factory()->create(['seller_id' => $store->id, 'name' => 'Banana Chips']);
    makeOrder('ORD-A', ['customer_name' => 'Aisyah', 'customer_email' => 'aisyah@example.com'], $chips);
    makeOrder('ORD-B', ['customer_name' => 'Budi']);

    $admin = filterAdmin();
    $numbers = fn (string $query) => collect($this->actingAs($admin)->getJson('/api/v1/admin/orders?'.$query)->assertOk()->json('data'))->pluck('order_number')->all();

    expect($numbers('search=ORD-B'))->toBe(['ORD-B'])
        ->and($numbers('search=aisyah%40example'))->toBe(['ORD-A'])
        ->and($numbers('search=banana'))->toBe(['ORD-A'])
        ->and($numbers('search=mak+cik'))->toBe(['ORD-A'])
        ->and($numbers('search=nothing-matches'))->toBe([]);
});

it('filters by status, store and date, sorts, and counts per status', function () {
    $storeA = Seller::factory()->create(['status' => SellerStatus::Active]);
    $storeB = Seller::factory()->create(['status' => SellerStatus::Active]);
    makeOrder('ORD-OLD', ['total' => 50, 'status' => OrderStatus::Paid, 'created_at' => now()->subDays(10)], Product::factory()->create(['seller_id' => $storeA->id]));
    makeOrder('ORD-BIG', ['total' => 90, 'status' => OrderStatus::Paid], Product::factory()->create(['seller_id' => $storeB->id]));
    makeOrder('ORD-NEW', ['total' => 20], Product::factory()->create(['seller_id' => $storeA->id]));

    $admin = filterAdmin();
    $get = fn (string $query) => $this->actingAs($admin)->getJson('/api/v1/admin/orders?'.$query)->assertOk();

    expect(collect($get('status=paid')->json('data'))->pluck('order_number')->all())->toBe(['ORD-BIG', 'ORD-OLD'])
        ->and(collect($get('seller_id='.$storeA->id)->json('data'))->pluck('order_number')->all())->toBe(['ORD-NEW', 'ORD-OLD'])
        ->and(collect($get('sort=total_desc')->json('data'))->pluck('order_number')->all())->toBe(['ORD-BIG', 'ORD-OLD', 'ORD-NEW'])
        ->and(collect($get('date_from='.now()->subDays(2)->toDateString())->json('data'))->pluck('order_number')->sort()->values()->all())->toBe(['ORD-BIG', 'ORD-NEW']);

    // Counts ignore the status filter itself but respect the others.
    $get('status=paid&seller_id='.$storeA->id)
        ->assertJsonPath('status_counts.paid', 1)
        ->assertJsonPath('status_counts.pending_payment', 1)
        ->assertJsonPath('status_counts_total', 2);
});

it('rejects invalid filter values', function () {
    $this->actingAs(filterAdmin())
        ->getJson('/api/v1/admin/orders?status=bogus&sort=random&date_from=yesterday')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'sort', 'date_from']);
});
