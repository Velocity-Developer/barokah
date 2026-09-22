<?php

use App\Enums\OrderStatus;
use App\Enums\SellerStatus;
use App\Jobs\SendOrderPlacedEmail;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\MailSettings;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function placedOrder(array $overrides = []): Order
{
    $seller = Seller::factory()->create(['status' => SellerStatus::Active, 'store_name' => 'Kerepek Store']);
    $product = Product::factory()->create(['seller_id' => $seller->id, 'name' => 'Banana Chips', 'price' => 12.5]);

    $order = Order::query()->create(array_merge([
        'order_number' => 'ORD-TEST-0001',
        'customer_name' => 'Aisyah',
        'customer_address' => 'No. 1 Jalan Test',
        'customer_state' => 'Selangor',
        'customer_post_code' => '40000',
        'customer_phone' => '0123456789',
        'customer_email' => 'aisyah@example.com',
        'shipping_address' => 'No. 1 Jalan Test',
        'shipping_state' => 'Selangor',
        'shipping_post_code' => '40000',
        'currency_code' => 'MYR',
        'subtotal' => 25,
        'discount_amount' => 0,
        'shipping_fee' => 5,
        'total' => 30,
        'status' => OrderStatus::PendingPayment,
        'shipping_method' => 'fixed',
        'expired_at' => now()->addMinutes(30),
    ], $overrides));

    $order->items()->create([
        'product_id' => $product->id,
        'seller_id' => $seller->id,
        'product_name_snapshot' => 'Banana Chips',
        'product_slug_snapshot' => $product->slug,
        'price_snapshot' => 12.5,
        'quantity' => 2,
        'subtotal' => 25,
        'discount_amount' => 0,
    ]);

    return $order;
}

it('queues a customer and an admin email when an order is placed', function () {
    Queue::fake();
    // Bank transfer is only offered once the bank details are set.
    app(SettingsService::class)->set('payment.bank_name', 'Maybank');
    app(SettingsService::class)->set('payment.bank_account_name', 'Barokah');
    app(SettingsService::class)->set('payment.bank_account_number', '1234567890');
    $product = Product::factory()->create(['stock' => 10, 'price' => 20]);

    $this->postJson('/api/v1/orders', [
        'product_id' => $product->id,
        'quantity' => 1,
        'buyer' => [
            'name' => 'Aisyah',
            'address' => 'No. 1 Jalan Test',
            'state' => 'Selangor',
            'post_code' => '40000',
            'phone' => '0123456789',
            'email' => 'aisyah@example.com',
        ],
        'shipping_address' => 'No. 1 Jalan Test',
        'shipping_state' => 'Selangor',
        'shipping_post_code' => '40000',
        'payment_method' => 'bank_transfer',
    ])->assertCreated();

    $orderId = Order::query()->value('id');

    Queue::assertPushed(SendOrderPlacedEmail::class, fn (SendOrderPlacedEmail $job) => $job->orderId === $orderId && $job->audience === OrderPlacedMail::AUDIENCE_CUSTOMER);
    Queue::assertPushed(SendOrderPlacedEmail::class, fn (SendOrderPlacedEmail $job) => $job->orderId === $orderId && $job->audience === OrderPlacedMail::AUDIENCE_ADMIN);
});

it('emails the customer at the checkout email, or their account email', function () {
    Mail::fake();
    $order = placedOrder();

    (new SendOrderPlacedEmail($order->id, OrderPlacedMail::AUDIENCE_CUSTOMER))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('aisyah@example.com') && $mail->audience === 'customer');

    $member = User::factory()->create(['email' => 'member@example.com']);
    $second = placedOrder(['order_number' => 'ORD-TEST-0002', 'customer_email' => null, 'user_id' => $member->id]);

    (new SendOrderPlacedEmail($second->id, OrderPlacedMail::AUDIENCE_CUSTOMER))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('member@example.com'));
});

it('skips the customer email for guests without an email address', function () {
    Mail::fake();
    $order = placedOrder(['customer_email' => null]);

    (new SendOrderPlacedEmail($order->id, OrderPlacedMail::AUDIENCE_CUSTOMER))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertNothingSent();
});

it('emails every admin, falling back to the contact email', function () {
    Mail::fake();
    $order = placedOrder();

    app(SettingsService::class)->set('contact.email', 'shop@example.com');
    (new SendOrderPlacedEmail($order->id, OrderPlacedMail::AUDIENCE_ADMIN))->handle(app(MailSettings::class), app(SettingsService::class));
    Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('shop@example.com') && $mail->audience === 'admin');

    User::factory()->create(['email' => 'boss@example.com'])->forceFill(['is_admin' => true])->save();
    User::factory()->create(['email' => 'ops@example.com'])->forceFill(['is_admin' => true])->save();
    (new SendOrderPlacedEmail($order->id, OrderPlacedMail::AUDIENCE_ADMIN))->handle(app(MailSettings::class), app(SettingsService::class));
    Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('boss@example.com') && $mail->hasTo('ops@example.com') && ! $mail->hasTo('shop@example.com'));
});

it('renders the order details for each audience', function () {
    app(SettingsService::class)->set('payment.bank_name', 'Maybank');
    app(SettingsService::class)->set('payment.bank_account_number', '1234567890');
    $order = placedOrder();
    $order->payment()->create(['payment_method' => 'bank_transfer', 'amount' => 30, 'currency' => 'MYR', 'status' => 'pending', 'payment_gateway' => 'manual']);

    $customer = (new OrderPlacedMail($order->fresh(), OrderPlacedMail::AUDIENCE_CUSTOMER))->render();
    expect($customer)
        ->toContain('ORD-TEST-0001', 'Banana Chips', 'Kerepek Store', 'RM 30.00', 'Bank transfer', 'Maybank', '1234567890')
        ->toContain('Delivery to:', 'No. 1 Jalan Test, Selangor 40000')
        ->toContain(route('checkout.confirmation', 'ORD-TEST-0001'));

    $admin = (new OrderPlacedMail($order->fresh(), OrderPlacedMail::AUDIENCE_ADMIN))->render();
    expect($admin)
        ->toContain('ORD-TEST-0001', 'Aisyah', '0123456789', 'aisyah@example.com', 'Guest checkout')
        ->toContain(route('admin.orders.show', 'ORD-TEST-0001'));
});

it('switches to the SMTP server from settings only when enabled', function () {
    $settings = app(SettingsService::class);
    $settings->set('email.from_address', 'orders@shop.test');
    $settings->set('email.smtp_host', 'smtp.shop.test');
    $settings->set('email.smtp_port', 465);
    $settings->set('email.smtp_encryption', 'ssl');
    $default = config('mail.default');

    app(MailSettings::class)->apply();
    expect(config('mail.default'))->toBe($default)
        ->and(config('mail.from.address'))->toBe('orders@shop.test');

    $settings->set('email.smtp_enabled', true);
    app(MailSettings::class)->apply();
    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.shop.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps');
});
