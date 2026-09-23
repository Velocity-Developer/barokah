<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Jobs\SendSellerOrderPaidEmail;
use App\Mail\SellerOrderPaidMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\MailSettings;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

function paidOrderSeller(string $email): Seller
{
    $owner = User::factory()->create(['email' => $email, 'is_active_as_seller' => true]);

    return Seller::factory()->create(['user_id' => $owner->id, 'status' => SellerStatus::Active]);
}

function pendingOrderFor(Seller ...$sellers): Order
{
    $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);

    foreach ($sellers as $seller) {
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'quantity' => 2,
            'price_snapshot' => 25,
            'subtotal' => 50,
        ]);
    }

    Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Pending]);

    return $order->refresh();
}

it('queues one email per store when a manual payment is verified', function () {
    Bus::fake();

    $first = paidOrderSeller('first@store.test');
    $second = paidOrderSeller('second@store.test');
    $order = pendingOrderFor($first, $second);

    app(PaymentService::class)->markPaid($order->payment);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);

    Bus::assertDispatchedTimes(SendSellerOrderPaidEmail::class, 2);
    Bus::assertDispatched(SendSellerOrderPaidEmail::class, fn (SendSellerOrderPaidEmail $job): bool => $job->sellerId === $first->id);
    Bus::assertDispatched(SendSellerOrderPaidEmail::class, fn (SendSellerOrderPaidEmail $job): bool => $job->sellerId === $second->id);
});

it('sends the store only its own lines', function () {
    Mail::fake();

    $mine = paidOrderSeller('mine@store.test');
    $other = paidOrderSeller('other@store.test');
    $order = pendingOrderFor($mine, $other);

    (new SendSellerOrderPaidEmail($order->id, $mine->id))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertSent(SellerOrderPaidMail::class, function (SellerOrderPaidMail $mail) use ($mine, $order): bool {
        $rendered = $mail->render();

        return collect($mail->to)->pluck('address')->all() === ['mine@store.test']
            && $mail->seller->is($mine)
            && str_contains($rendered, $order->order_number)
            && ! str_contains($rendered, $order->items->firstWhere('seller_id', '!=', $mine->id)->product_name_snapshot);
    });
});

it('stays quiet while seller notifications are switched off', function () {
    Mail::fake();

    $settings = app(SettingsService::class);
    $settings->set('email.seller_notifications_enabled', false);
    $settings->forget();

    $seller = paidOrderSeller('quiet@store.test');
    $order = pendingOrderFor($seller);

    (new SendSellerOrderPaidEmail($order->id, $seller->id))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertNothingSent();
});

it('does not email again when the same payment is confirmed twice', function () {
    Bus::fake();

    $seller = paidOrderSeller('once@store.test');
    $order = pendingOrderFor($seller);

    $payments = app(PaymentService::class);
    $payments->markPaid($order->payment);
    $payments->markPaid($order->payment->fresh());

    Bus::assertDispatchedTimes(SendSellerOrderPaidEmail::class, 1);
});
