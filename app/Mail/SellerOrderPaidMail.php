<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your order is paid" email for one store. Only that store's lines and its
 * share of the money are shown; other sellers in the same checkout stay
 * private (spec §14.3).
 */
class SellerOrderPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Seller $seller) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order {$this->order->order_number} is paid — please prepare it");
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);

        $items = $this->order->items->where('seller_id', $this->seller->id);
        $storeTotal = (float) $items->sum('subtotal');

        return new Content(
            markdown: 'mail.orders.paid-seller',
            with: [
                'order' => $this->order,
                'seller' => $this->seller,
                'siteName' => (string) $settings->get('branding.site_name', config('app.name')),
                'items' => $items->map(fn (OrderItem $item): array => [
                    'name' => $this->tableCell($item->product_name_snapshot),
                    'quantity' => $item->quantity,
                    'price' => $this->money((float) $item->price_snapshot),
                    'subtotal' => $this->money((float) $item->subtotal),
                ])->values(),
                'storeTotal' => $this->money($storeTotal),
                'paidAt' => $this->order->payment?->paid_at
                    ?->timezone((string) $settings->get('localization.timezone', config('app.timezone')))
                    ->format('j M Y, H:i'),
                'deliveryAddress' => $this->deliveryAddress(),
                'orderUrl' => route('seller.orders.show', $this->order->order_number),
            ],
        );
    }

    /**
     * Shipping address, or the buyer's address when no separate one was given.
     */
    private function deliveryAddress(): ?string
    {
        $order = $this->order;

        $parts = filled($order->shipping_address)
            ? [$order->shipping_address, $order->shipping_city, trim($order->shipping_state.' '.$order->shipping_post_code)]
            : [$order->customer_address, $order->customer_city, trim($order->customer_state.' '.$order->customer_post_code)];

        $address = implode(', ', array_filter($parts, fn (?string $part): bool => filled($part)));

        return $address !== '' ? $address : null;
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

    /** Keep product names from breaking the Markdown table. */
    private function tableCell(string $value): string
    {
        return str_replace(['|', "\n"], ['/', ' '], $value);
    }
}
