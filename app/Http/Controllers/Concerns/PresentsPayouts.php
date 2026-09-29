<?php

namespace App\Http\Controllers\Concerns;

use App\Models\OrderSellerTracking;
use App\Models\SellerPayout;
use App\Services\CurrencyFormatter;
use App\Services\PayoutService;

/**
 * Payout rows shared by the seller and admin payout pages.
 */
trait PresentsPayouts
{
    /**
     * @return array<string, mixed>
     */
    protected function payoutRow(SellerPayout $payout, CurrencyFormatter $currency): array
    {
        return [
            'id' => $payout->id,
            'reference' => $payout->reference,
            'status' => $payout->status->value,
            'store_name' => $payout->seller?->store_name,
            'seller_id' => $payout->seller_id,
            'orders_count' => $payout->orders_count,
            'product_amount_formatted' => $currency->format((float) $payout->product_amount),
            'shipping_amount_formatted' => $currency->format((float) $payout->shipping_amount),
            'commission_rate' => (float) $payout->commission_rate,
            'commission_amount_formatted' => $currency->format((float) $payout->commission_amount),
            'net_amount_formatted' => $currency->format((float) $payout->net_amount),
            'bank_account' => $payout->bank_account,
            'seller_note' => $payout->seller_note,
            'admin_note' => $payout->admin_note,
            'transfer_reference' => $payout->transfer_reference,
            'proof_url' => $payout->proof_path !== null ? route('payouts.proof', $payout) : null,
            'requested_at' => $payout->created_at?->toIso8601String(),
            'approved_at' => $payout->approved_at?->toIso8601String(),
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'rejected_at' => $payout->rejected_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shipmentRow(OrderSellerTracking $tracking, PayoutService $payouts, CurrencyFormatter $currency): array
    {
        $earning = $payouts->shipmentEarning($tracking);

        return [
            'id' => $tracking->id,
            'order_number' => $tracking->order->order_number,
            'delivered_at' => $tracking->delivered_at?->toIso8601String(),
            'product_amount_formatted' => $currency->format($earning['product']),
            'shipping_amount_formatted' => $currency->format($earning['shipping']),
            'total_formatted' => $currency->format($earning['product'] + $earning['shipping']),
        ];
    }
}
