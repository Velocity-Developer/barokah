<?php

namespace App\Jobs;

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\User;
use App\Services\MailSettings;
use App\Services\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * One job per audience, so a failing admin mailbox never re-sends the
 * customer's email on retry (and vice versa).
 */
class SendOrderPlacedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $orderId, public string $audience) {}

    public function handle(MailSettings $mailSettings, SettingsService $settings): void
    {
        $order = Order::query()->with('user')->find($this->orderId);

        if ($order === null) {
            return;
        }

        $recipients = $this->audience === OrderPlacedMail::AUDIENCE_ADMIN
            ? $this->adminRecipients($settings)
            : array_filter([$order->customer_email ?: $order->user?->email]);

        if ($recipients === []) {
            return;
        }

        $mailSettings->apply();

        Mail::to($recipients)->send(new OrderPlacedMail($order, $this->audience));
    }

    /**
     * Admin accounts; the contact email from Settings when there is none.
     *
     * @return list<string>
     */
    private function adminRecipients(SettingsService $settings): array
    {
        $admins = User::query()->where('is_admin', true)->pluck('email')->filter()->unique()->values()->all();

        if ($admins !== []) {
            return $admins;
        }

        $contact = $settings->get('contact.email');

        return is_string($contact) && $contact !== '' ? [$contact] : [];
    }
}
