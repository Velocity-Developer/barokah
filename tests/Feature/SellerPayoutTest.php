<?php

use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\SellerStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSellerTracking;
use App\Models\Seller;
use App\Models\SellerPayout;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function payoutSeller(?string $bankAccount = 'Maybank 1234567890 a/n Toko Payout'): Seller
{
    $user = User::factory()->create(['is_active_as_seller' => true]);

    return Seller::factory()->create(['user_id' => $user->id, 'status' => SellerStatus::Active, 'bank_account' => $bankAccount]);
}

function payoutAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

/**
 * One order line of the store worth $amount plus a shipment with $shipping.
 *
 * @param  array<string, mixed>  $orderAttributes
 */
function deliveredShipment(Seller $seller, float $amount, float $shipping = 5.0, string $trackingStatus = 'delivered', OrderStatus $orderStatus = OrderStatus::Paid, array $orderAttributes = []): OrderSellerTracking
{
    $order = Order::factory()->create(['status' => $orderStatus, ...$orderAttributes]);
    OrderItem::factory()->create(['order_id' => $order->id, 'seller_id' => $seller->id, 'price_snapshot' => $amount, 'quantity' => 1, 'subtotal' => $amount]);

    return $order->sellerTrackings()->create([
        'seller_id' => $seller->id,
        'shipping_fee' => $shipping,
        'tracking_status' => $trackingStatus,
        'delivered_at' => $trackingStatus === 'delivered' ? now() : null,
    ]);
}

function setCommission(string $rate): void
{
    app(SettingsService::class)->set('marketplace.commission_rate', $rate);
}

it('counts only delivered shipments of paid orders in the balance', function () {
    setCommission('10');
    $seller = payoutSeller();
    deliveredShipment($seller, 100, 5);
    deliveredShipment($seller, 50, 0, trackingStatus: 'picked_up');
    deliveredShipment($seller, 70, 0, orderStatus: OrderStatus::PendingPayment);
    deliveredShipment(payoutSeller(), 999, 0);

    $this->actingAs($seller->user)
        ->get(route('seller.payouts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Payouts/Index')
            ->where('balance.orders_count', 1)
            // 100 - 10% commission + 5 shipping.
            ->where('balance.net', 95)
            ->has('available_orders', 1));
});

it('lets a seller request the whole balance and locks those orders', function () {
    setCommission('10');
    $seller = payoutSeller();
    $first = deliveredShipment($seller, 100, 5);
    $second = deliveredShipment($seller, 200, 10);

    $this->actingAs($seller->user)
        ->post(route('seller.payouts.store'), ['note' => 'Thanks'])
        ->assertSessionHasNoErrors();

    $payout = SellerPayout::query()->sole();
    expect($payout->status)->toBe(PayoutStatus::Pending)
        ->and((float) $payout->product_amount)->toBe(300.0)
        ->and((float) $payout->shipping_amount)->toBe(15.0)
        ->and((float) $payout->commission_amount)->toBe(30.0)
        ->and((float) $payout->net_amount)->toBe(285.0)
        ->and($payout->orders_count)->toBe(2)
        ->and($payout->bank_account)->toBe('Maybank 1234567890 a/n Toko Payout')
        ->and($first->refresh()->payout_id)->toBe($payout->id)
        ->and($second->refresh()->payout_id)->toBe($payout->id);

    // Nothing left to withdraw, so a second request is refused.
    $this->actingAs($seller->user)
        ->post(route('seller.payouts.store'))
        ->assertSessionHasErrors('payout');
    expect(SellerPayout::query()->count())->toBe(1);
});

it('only deducts a coupon discount when the coupon belongs to the store', function () {
    $seller = payoutSeller();
    deliveredShipment($seller, 100, 0, orderAttributes: ['discount_amount' => 20, 'coupon_snapshot' => ['seller_id' => $seller->id]]);
    deliveredShipment($seller, 100, 0, orderAttributes: ['discount_amount' => 20, 'coupon_snapshot' => ['seller_id' => null]]);

    $this->actingAs($seller->user)->post(route('seller.payouts.store'));

    expect((float) SellerPayout::query()->sole()->net_amount)->toBe(180.0);
});

it('asks for a bank account before a payout can be requested', function () {
    $seller = payoutSeller(bankAccount: null);
    deliveredShipment($seller, 100);

    $this->actingAs($seller->user)
        ->post(route('seller.payouts.store'))
        ->assertSessionHasErrors('payout');

    expect(SellerPayout::query()->count())->toBe(0);
});

it('takes a payout from request through approval to a sent transfer with proof', function () {
    Storage::fake('local');
    $seller = payoutSeller();
    deliveredShipment($seller, 100, 5);
    $admin = payoutAdmin();

    $this->actingAs($seller->user)->post(route('seller.payouts.store'));
    $payout = SellerPayout::query()->sole();

    // Proof cannot be uploaded before approval.
    $this->actingAs($admin)
        ->post(route('admin.payouts.paid', $payout), ['proof' => UploadedFile::fake()->image('transfer.jpg')])
        ->assertSessionHasErrors('payout');

    $this->actingAs($admin)->post(route('admin.payouts.approve', $payout))->assertSessionHasNoErrors();
    expect($payout->refresh()->status)->toBe(PayoutStatus::Approved);

    $this->actingAs($admin)
        ->post(route('admin.payouts.paid', $payout), [
            'proof' => UploadedFile::fake()->image('transfer.jpg'),
            'transfer_reference' => 'MBB-778899',
        ])
        ->assertSessionHasNoErrors();

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->transfer_reference)->toBe('MBB-778899')
        ->and($payout->paid_at)->not->toBeNull();
    Storage::disk('local')->assertExists($payout->proof_path);

    // The store follows its request and can open the proof.
    $this->actingAs($seller->user)
        ->get(route('seller.payouts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('payouts.0.status', 'paid')
            ->where('payouts.0.transfer_reference', 'MBB-778899')
            ->where('payouts.0.proof_url', route('payouts.proof', $payout)));
    $this->actingAs($seller->user)->get(route('payouts.proof', $payout))->assertOk();
});

it('keeps the transfer proof away from other stores', function () {
    Storage::fake('local');
    $seller = payoutSeller();
    deliveredShipment($seller, 100);
    $admin = payoutAdmin();
    $this->actingAs($seller->user)->post(route('seller.payouts.store'));
    $payout = SellerPayout::query()->sole();
    $this->actingAs($admin)->post(route('admin.payouts.approve', $payout));
    $this->actingAs($admin)->post(route('admin.payouts.paid', $payout), ['proof' => UploadedFile::fake()->image('transfer.jpg')]);

    $this->actingAs(payoutSeller()->user)->get(route('payouts.proof', $payout))->assertForbidden();
    $this->actingAs($admin)->get(route('payouts.proof', $payout))->assertOk();
});

it('puts the orders back in the balance when a payout is rejected', function () {
    $seller = payoutSeller();
    $shipment = deliveredShipment($seller, 100);
    $this->actingAs($seller->user)->post(route('seller.payouts.store'));
    $payout = SellerPayout::query()->sole();

    $this->actingAs(payoutAdmin())
        ->post(route('admin.payouts.reject', $payout), [])
        ->assertSessionHasErrors('reason');

    $this->actingAs(payoutAdmin())
        ->post(route('admin.payouts.reject', $payout), ['reason' => 'Bank account name does not match.'])
        ->assertSessionHasNoErrors();

    expect($payout->refresh()->status)->toBe(PayoutStatus::Rejected)
        ->and($payout->admin_note)->toBe('Bank account name does not match.')
        ->and($shipment->refresh()->payout_id)->toBeNull();

    $this->actingAs($seller->user)
        ->get(route('seller.payouts.index'))
        ->assertInertia(fn (Assert $page) => $page->where('balance.orders_count', 1));
});

it('keeps payout pages to their own roles', function () {
    $seller = payoutSeller();
    deliveredShipment($seller, 100);
    $this->actingAs($seller->user)->post(route('seller.payouts.store'));
    $payout = SellerPayout::query()->sole();

    $this->actingAs($seller->user)->get(route('admin.payouts.index'))->assertForbidden();
    $this->actingAs($seller->user)->post(route('admin.payouts.approve', $payout))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('seller.payouts.index'))->assertForbidden();

    $this->actingAs(payoutAdmin())
        ->get(route('admin.payouts.index', ['status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payouts/Index')
            ->where('status_counts.pending', 1)
            ->has('payouts.data', 1));
    $this->actingAs(payoutAdmin())
        ->get(route('admin.payouts.show', $payout))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Payouts/Show')->has('orders', 1));
});
