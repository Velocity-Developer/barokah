<?php

use App\Enums\OrderStatus;
use App\Jobs\SendOrderPlacedEmail;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\User;
use App\Services\MailSettings;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Mail;

function settingsAdmin(): User
{
    $admin = User::factory()->create(['email' => 'boss@barokah.test']);
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function notifiedOrder(): Order
{
    return Order::factory()->create([
        'status' => OrderStatus::PendingPayment,
        'customer_email' => 'buyer@example.com',
    ]);
}

it('validates the admin notification email list', function () {
    $admin = settingsAdmin();

    $this->actingAs($admin)
        ->putJson('/api/v1/admin/settings', ['settings' => [
            ['key' => 'email.admin_notification_recipients', 'value' => 'orders@barokah.test, not-an-email'],
        ]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('settings.email.admin_notification_recipients');

    $this->actingAs($admin)
        ->putJson('/api/v1/admin/settings', ['settings' => [
            ['key' => 'email.admin_notification_recipients', 'value' => 'orders@barokah.test, owner@barokah.test'],
        ]])
        ->assertOk();

    expect(app(SettingsService::class)->get('email.admin_notification_recipients'))
        ->toBe('orders@barokah.test, owner@barokah.test');
});

it('sends admin order emails to the configured addresses instead of the admin accounts', function () {
    Mail::fake();
    settingsAdmin();
    $settings = app(SettingsService::class);
    $settings->set('email.admin_notification_recipients', 'orders@barokah.test, owner@barokah.test');
    $settings->forget();

    (new SendOrderPlacedEmail(notifiedOrder()->id, OrderPlacedMail::AUDIENCE_ADMIN))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail): bool => collect($mail->to)->pluck('address')->all() === ['orders@barokah.test', 'owner@barokah.test']);
});

it('skips the admin email when admin notifications are switched off', function () {
    Mail::fake();
    settingsAdmin();
    $settings = app(SettingsService::class);
    $settings->set('email.admin_notifications_enabled', false);
    $settings->forget();

    (new SendOrderPlacedEmail(notifiedOrder()->id, OrderPlacedMail::AUDIENCE_ADMIN))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertNothingSent();
});

it('falls back to the admin accounts when no notification address is set', function () {
    Mail::fake();
    settingsAdmin();

    (new SendOrderPlacedEmail(notifiedOrder()->id, OrderPlacedMail::AUDIENCE_ADMIN))->handle(app(MailSettings::class), app(SettingsService::class));

    Mail::assertSent(OrderPlacedMail::class, fn (OrderPlacedMail $mail): bool => collect($mail->to)->pluck('address')->all() === ['boss@barokah.test']);
});
