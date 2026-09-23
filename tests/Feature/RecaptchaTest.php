<?php

use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

function enableRecaptcha(array $overrides = []): void
{
    $settings = app(SettingsService::class);

    foreach (array_merge([
        'security.recaptcha_enabled' => true,
        'security.recaptcha_site_key' => 'site-key',
        'security.recaptcha_secret_key' => 'secret-key',
        'security.recaptcha_on_login' => true,
        'security.recaptcha_on_guest_checkout' => true,
    ], $overrides) as $key => $value) {
        $settings->set($key, $value);
    }

    $settings->forget();
}

/**
 * One stub whose verdict can change, because a second Http::fake() would not
 * replace the first stub.
 */
function fakeGoogle(): stdClass
{
    $verdict = new stdClass;
    $verdict->success = true;

    Http::fake(fn () => Http::response(['success' => $verdict->success, 'score' => 0.9]));

    return $verdict;
}

/** Bank transfer only counts as enabled once its details are filled in. */
function enableBankTransfer(): void
{
    $settings = app(SettingsService::class);
    $settings->set('payment.bank_transfer_enabled', true);
    $settings->set('payment.bank_name', 'Maybank');
    $settings->set('payment.bank_account_name', 'Barokah');
    $settings->set('payment.bank_account_number', '1234567890');
    $settings->forget();
}

it('keeps the secret key out of the public settings', function () {
    enableRecaptcha();

    $public = $this->getJson('/api/v1/settings/public')->assertOk()->json();

    expect($public)->toHaveKey('security.recaptcha_site_key')
        ->and($public)->not->toHaveKey('security.recaptcha_secret_key');
});

it('blocks a login when the captcha fails, and allows it when it passes', function () {
    enableRecaptcha();
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    $verdict = fakeGoogle();
    $verdict->success = false;

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password', 'recaptcha_token' => 'bad'])
        ->assertSessionHasErrors('recaptcha_token');
    $this->assertGuest();

    $verdict->success = true;
    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password', 'recaptcha_token' => 'good'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

it('skips the login captcha while it is switched off there', function () {
    enableRecaptcha(['security.recaptcha_on_login' => false]);
    $user = User::factory()->create(['password' => bcrypt('secret-password')]);

    Http::fake();

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    Http::assertNothingSent();
});

it('asks a guest buyer for the captcha', function () {
    enableRecaptcha();
    enableBankTransfer();
    $product = Product::factory()->create(['stock' => 5, 'price' => 20]);
    $verdict = fakeGoogle();

    $payload = fn (array $extra = []) => array_merge([
        'product_id' => $product->id,
        'quantity' => 1,
        'buyer' => [
            'name' => 'Guest Buyer',
            'address' => 'No. 1, Jalan Satu',
            'state' => 'Selangor',
            'city' => 'Petaling Jaya',
            'post_code' => '47300',
            'phone' => '0123456789',
        ],
        'shipping_address' => 'No. 1, Jalan Satu',
        'shipping_state' => 'Selangor',
        'shipping_city' => 'Petaling Jaya',
        'shipping_post_code' => '47300',
        'payment_method' => 'bank_transfer',
    ], $extra);

    $verdict->success = false;
    $this->postJson('/api/v1/orders', $payload(['recaptcha_token' => 'bad']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('recaptcha_token');

    $verdict->success = true;
    $this->postJson('/api/v1/orders', $payload(['recaptcha_token' => 'good']))->assertCreated();
});

it('does not ask a signed-in buyer for the captcha', function () {
    enableRecaptcha();
    enableBankTransfer();
    $product = Product::factory()->create(['stock' => 5, 'price' => 20]);
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'quantity' => 1,
            'buyer' => [
                'name' => 'Signed In',
                'address' => 'No. 2, Jalan Dua',
                'state' => 'Selangor',
                'city' => 'Petaling Jaya',
                'post_code' => '47300',
                'phone' => '0123456789',
            ],
            'shipping_address' => 'No. 2, Jalan Dua',
            'shipping_state' => 'Selangor',
            'shipping_city' => 'Petaling Jaya',
            'shipping_post_code' => '47300',
            'payment_method' => 'bank_transfer',
        ])
        ->assertCreated();

    Http::assertNothingSent();
});

it('turns guests away when guest checkout is switched off', function () {
    $settings = app(SettingsService::class);
    $settings->set('checkout.guest_checkout_enabled', false);
    $settings->forget();
    enableBankTransfer();

    $product = Product::factory()->create(['stock' => 5, 'price' => 20]);

    $this->postJson('/api/v1/orders', [
        'product_id' => $product->id,
        'quantity' => 1,
        'buyer' => [
            'name' => 'Guest Buyer',
            'address' => 'No. 1, Jalan Satu',
            'state' => 'Selangor',
            'city' => 'Petaling Jaya',
            'post_code' => '47300',
            'phone' => '0123456789',
        ],
        'shipping_address' => 'No. 1, Jalan Satu',
        'shipping_state' => 'Selangor',
        'shipping_city' => 'Petaling Jaya',
        'shipping_post_code' => '47300',
        'payment_method' => 'bank_transfer',
    ])->assertForbidden();
});
