<?php

namespace App\Http\Controllers\Seller;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Concerns\PresentsPayouts;
use App\Http\Controllers\Controller;
use App\Models\OrderSellerTracking;
use App\Models\SellerPayout;
use App\Services\CurrencyFormatter;
use App\Services\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's balance and its payout requests. Transfers happen outside the
 * site; the store follows each request here until the admin uploads proof.
 */
class PayoutController extends Controller
{
    use PresentsPayouts;

    public function index(Request $request, PayoutService $payouts, CurrencyFormatter $currency): Response
    {
        $seller = $request->user()->seller;
        abort_if($seller === null, 404);

        $available = $payouts->availableTrackings($seller);
        $rate = $payouts->commissionRate();
        $totals = $payouts->totals($available, $rate);

        $history = $seller->payouts()->with('seller:id,store_name')->latest()->orderByDesc('id')->get();

        return Inertia::render('Seller/Payouts/Index', [
            'balance' => [
                'orders_count' => $totals['count'],
                'product_formatted' => $currency->format($totals['product']),
                'shipping_formatted' => $currency->format($totals['shipping']),
                'commission_rate' => $rate,
                'commission_formatted' => $currency->format($totals['commission']),
                'net' => $totals['net'],
                'net_formatted' => $currency->format($totals['net']),
                'in_process_formatted' => $currency->format((float) $history->whereIn('status', PayoutStatus::open())->sum('net_amount')),
                'paid_out_formatted' => $currency->format((float) $history->where('status', PayoutStatus::Paid)->sum('net_amount')),
            ],
            'bank_account' => $seller->bank_account,
            'available_orders' => $available->map(fn (OrderSellerTracking $tracking): array => $this->shipmentRow($tracking, $payouts, $currency))->values(),
            'payouts' => $history->map(fn (SellerPayout $payout): array => $this->payoutRow($payout, $currency))->values(),
        ]);
    }

    public function store(Request $request, PayoutService $payouts): RedirectResponse
    {
        $seller = $request->user()->seller;
        abort_if($seller === null, 404);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $payout = $payouts->request($seller, $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout :reference requested.', ['reference' => $payout->reference])]);

        return back();
    }
}
