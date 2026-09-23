<?php

use App\Enums\OrderStatus;
use App\Enums\SellerStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function customerAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

it('splits customers into buyers, store owners and admins', function () {
    $buyer = User::factory()->create(['name' => 'Farah Buyer', 'city' => 'Klang', 'state' => 'Selangor']);
    $owner = User::factory()->create(['name' => 'Hakim Owner']);
    Seller::factory()->create(['user_id' => $owner->id, 'store_name' => 'Kedai Hakim', 'status' => SellerStatus::Active]);
    $admin = customerAdmin();

    $get = fn (string $query) => $this->actingAs($admin)->getJson('/api/v1/admin/users?'.$query)->assertOk();

    $get('')
        ->assertJsonPath('segment_counts.buyers', 1)
        ->assertJsonPath('segment_counts.sellers', 1)
        ->assertJsonPath('segment_counts.admins', 1)
        ->assertJsonPath('segment_counts_total', 3);

    $get('segment=sellers')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $owner->id)
        ->assertJsonPath('data.0.seller.store_name', 'Kedai Hakim');

    // The store name and the city are searchable, not only name and email.
    $get('search=kedai hakim')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owner->id);
    $get('search=klang')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $buyer->id);
});

it('counts orders and paid spend per customer, highest spender first', function () {
    $spender = User::factory()->create();
    $smallSpender = User::factory()->create();

    Order::factory()->create(['user_id' => $spender->id, 'status' => OrderStatus::Completed, 'total' => 300]);
    Order::factory()->create(['user_id' => $spender->id, 'status' => OrderStatus::Paid, 'total' => 200]);
    // Unpaid orders still count as orders, but not as money spent.
    Order::factory()->create(['user_id' => $spender->id, 'status' => OrderStatus::PendingPayment, 'total' => 999]);
    Order::factory()->create(['user_id' => $smallSpender->id, 'status' => OrderStatus::Completed, 'total' => 50]);

    $data = $this->actingAs(customerAdmin())
        ->getJson('/api/v1/admin/users?sort=spend_desc')
        ->assertOk()
        ->json('data');

    expect($data[0]['id'])->toBe($spender->id)
        ->and($data[0]['orders_count'])->toBe(3)
        ->and((float) $data[0]['total_spent'])->toBe(500.0)
        ->and($data[1]['id'])->toBe($smallSpender->id)
        ->and((float) $data[1]['total_spent'])->toBe(50.0);
});

it('shows customer stats, recent orders and the store they own', function () {
    $owner = User::factory()->create();
    $store = Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);
    Product::factory()->create(['seller_id' => $store->id]);
    Order::factory()->create(['user_id' => $owner->id, 'status' => OrderStatus::Completed, 'total' => 120]);
    $owner->followedSellers()->attach(Seller::factory()->create());

    $this->withoutVite()->actingAs(customerAdmin())
        ->get(route('admin.users.show', $owner))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Show')
            ->where('stats.orders', 1)
            ->where('stats.spent_formatted', 'RM 120.00')
            ->where('stats.followed_stores', 1)
            ->where('stats.favorite_products', 0)
            ->has('recent_orders', 1)
            ->where('user.seller.store_name', $store->store_name)
            ->where('user.seller.products_count', 1));
});
