<?php

namespace App\Mail;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PaymentService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Order placed" email. The customer copy has payment instructions; the
 * admin copy has the customer's contact and delivery details.
 */
class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public const AUDIENCE_CUSTOMER = 'customer';

    public const AUDIENCE_ADMIN = 'admin';

    public function __construct(public Order $order, public string $audience) {}

    public function envelope(): Envelope
    {
        $siteName = (string) app(SettingsService::class)->get('branding.site_name', config('app.name'));

        return new Envelope(
            subject: $this->audience === self::AUDIENCE_ADMIN
                ? "New order {$this->order->order_number} — {$this->money((float) $this->order->total)}"
                : "Your {$siteName} order {$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);
        $order = $this->order->loadMissing(['items.seller', 'payment']);
        $method = $order->payment?->payment_method;

        return new Content(
            markdown: $this->audience === self::AUDIENCE_ADMIN ? 'mail.orders.placed-admin' : 'mail.orders.placed-customer',
            with: [
                'order' => $order,
                'siteName' => (string) $settings->get('branding.site_name', config('app.name')),
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'name' => $this->tableCell($item->product_name_snapshot),
                    'store' => $this->tableCell((string) $item->seller?->store_name),
                    'quantity' => $item->quantity,
                    'subtotal' => $this->money((float) $item->subtotal),
                ]),
                'subtotal' => $this->money((float) $order->subtotal),
                'discount' => (float) $order->discount_amount > 0 ? $this->money((float) $order->discount_amount) : null,
                'shipping' => $this->money((float) $order->shipping_fee),
                'total' => $this->money((float) $order->total),
                'paymentLabel' => $this->paymentLabel($method),
                'bankDetails' => $method === PaymentMethod::BankTransfer ? app(PaymentService::class)->manualPaymentDetails() : null,
                'payBy' => $order->expired_at?->timezone((string) $settings->get('localization.timezone', config('app.timezone')))->format('j M Y, H:i'),
                'deliveryAddress' => $this->deliveryAddress($order),
                'orderUrl' => route('checkout.confirmation', $order->order_number),
                'adminUrl' => route('admin.orders.show', $order->order_number),
            ],
        );
    }

    /**
     * Shipping address, or the buyer's address when no separate one was given.
     */
    private function deliveryAddress(Order $order): ?string
    {
        $parts = filled($order->shipping_address)
            ? [$order->shipping_address, $order->shipping_city, trim($order->shipping_state.' '.$order->shipping_post_code)]
            : [$order->customer_address, $order->customer_city, trim($order->customer_state.' '.$order->customer_post_code)];

        $address = implode(', ', array_filter($parts, fn (?string $part): bool => filled($part)));

        return $address !== '' ? $address : null;
    }

    private function paymentLabel(?PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Fpx => 'FPX online banking',
            PaymentMethod::DuitNow => 'DuitNow',
            PaymentMethod::BankTransfer => 'Bank transfer',
            PaymentMethod::QrCode => 'QR code',
            default => 'Not selected',
        };
    }

    private function money(float $amount): string
    {
        $settings = app(SettingsService::class);

        return trim($settings->get('currency.symbol', 'RM').' '.number_format(
            $amount,
            (int) $settings->get('currency.decimals', 2),
            (string) $settings->get('currency.decimal_separator', '.'),
            (string) $settings->get('currency.thousands_separator', ','),
        ));
    }

    /** Keep product/store names from breaking the Markdown table. */
    private function tableCell(string $value): string
    {
        return str_replace(['|', "\n"], ['/', ' '], $value);
    }
}
