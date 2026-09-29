<?php

namespace App\Http\Controllers\Admin;

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
 * Admin review of seller payout requests: approve, reject, then upload the
 * proof of the transfer made outside the site.
 */
class PayoutController extends Controller
{
    use PresentsPayouts;

    public function index(Request $request, CurrencyFormatter $currency): Response
    {
        $status = PayoutStatus::tryFrom((string) $request->query('status', ''));

        $counts = SellerPayout::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $payouts = SellerPayout::query()
            ->with('seller:id,store_name')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            // Waiting requests first, oldest on top; history newest first.
            ->orderByRaw("case when status in ('pending', 'approved') then 0 else 1 end")
            ->orderByRaw("case when status in ('pending', 'approved') then created_at end asc")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SellerPayout $payout): array => $this->payoutRow($payout, $currency));

        return Inertia::render('Admin/Payouts/Index', [
            'payouts' => $payouts,
            'filters' => ['status' => $status?->value ?? ''],
            'status_counts' => collect(PayoutStatus::cases())
                ->mapWithKeys(fn (PayoutStatus $case): array => [$case->value => (int) ($counts[$case->value] ?? 0)])
                ->put('all', (int) $counts->sum()),
        ]);
    }

    public function show(SellerPayout $payout, PayoutService $payouts, CurrencyFormatter $currency): Response
    {
        $payout->load(['seller.user', 'processor:id,name', 'trackings.order.items']);

        return Inertia::render('Admin/Payouts/Show', [
            'payout' => [
                ...$this->payoutRow($payout, $currency),
                'processed_by' => $payout->processor?->name,
                'seller' => [
                    'id' => $payout->seller->id,
                    'store_name' => $payout->seller->store_name,
                    'owner_name' => $payout->seller->user?->name,
                    'owner_email' => $payout->seller->user?->email,
                    'phone' => $payout->seller->phone,
                    'whatsapp' => $payout->seller->whatsapp,
                    'current_bank_account' => $payout->seller->bank_account,
                ],
            ],
            // Released when rejected, so a rejected payout lists no orders.
            'orders' => $payout->trackings
                ->sortBy('delivered_at')
                ->map(fn (OrderSellerTracking $tracking): array => $this->shipmentRow($tracking, $payouts, $currency))
                ->values(),
        ]);
    }

    public function approve(Request $request, SellerPayout $payout, PayoutService $payouts): RedirectResponse
    {
        $payouts->approve($payout, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout :reference approved. Upload the transfer proof once the money is sent.', ['reference' => $payout->reference])]);

        return back();
    }

    public function reject(Request $request, SellerPayout $payout, PayoutService $payouts): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $payouts->reject($payout, $request->user(), $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout :reference rejected. Its orders are back in the store balance.', ['reference' => $payout->reference])]);

        return back();
    }

    public function markPaid(Request $request, SellerPayout $payout, PayoutService $payouts): RedirectResponse
    {
        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'transfer_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $payouts->markPaid($payout, $request->user(), $request->file('proof'), $validated['transfer_reference'] ?? null, $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout :reference marked as sent.', ['reference' => $payout->reference])]);

        return back();
    }
}
