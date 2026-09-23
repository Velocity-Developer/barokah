<?php

namespace App\Jobs;

use App\Mail\SellerOrderPaidMail;
use App\Models\Order;
use App\Models\Seller;
use App\Services\MailSettings;
use App\Services\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Tells one store that an order containing its products has been paid.
 * One job per store, so a bad mailbox in one store never blocks the rest.
 */
class SendSellerOrderPaidEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $orderId, public int $sellerId) {}

    public function handle(MailSettings $mailSettings, SettingsService $settings): void
    {
        if (! (bool) $settings->get('email.seller_notifications_enabled', true)) {
            return;
        }

        $order = Order::query()->with(['items', 'payment'])->find($this->orderId);
        $seller = Seller::query()->with('user')->find($this->sellerId);

        if ($order === null || $seller === null) {
            return;
        }

        // The store's own contact address, else the owner's account email.
        $recipient = $seller->user?->email;

        if ($recipient === null || $recipient === '') {
            return;
        }

        $mailSettings->apply();

        Mail::to($recipient)->send(new SellerOrderPaidMail($order, $seller));
    }
}
