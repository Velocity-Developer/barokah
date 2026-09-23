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

        $isAdmin = $this->audience === OrderPlacedMail::AUDIENCE_ADMIN;
        $enabled = (bool) $settings->get($isAdmin ? 'email.admin_notifications_enabled' : 'email.customer_notifications_enabled', true);

        if (! $enabled) {
            return;
        }

        $recipients = $isAdmin
            ? $this->adminRecipients($settings)
            : array_values(array_filter([$order->customer_email ?: $order->user?->email]));

        if ($recipients === []) {
            return;
        }

        $mailSettings->apply();

        Mail::to($recipients)->send(new OrderPlacedMail($order, $this->audience));
    }

    /**
     * The notification addresses from Settings; the admin accounts when they
     * are empty, and the contact email as a last resort.
     *
     * @return list<string>
     */
    private function adminRecipients(SettingsService $settings): array
    {
        $configured = $this->emailList($settings->get('email.admin_notification_recipients'));

        if ($configured !== []) {
            return $configured;
        }

        $admins = User::query()->where('is_admin', true)->pluck('email')->filter()->unique()->values()->all();

        if ($admins !== []) {
            return $admins;
        }

        return $this->emailList($settings->get('contact.email'));
    }

    /**
     * @return list<string>
     */
    private function emailList(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/[,;]+/', $value) ?: [])
            ->map(fn (string $address): string => trim($address))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
