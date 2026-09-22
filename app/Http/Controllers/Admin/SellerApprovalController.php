<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin review of pending seller applications.
 */
class SellerApprovalController extends Controller
{
    public function index(): Response
    {
        $applications = Seller::query()
            ->where('status', SellerStatus::Pending)
            ->with('user')
            ->oldest()
            ->get()
            ->map(fn (Seller $seller): array => [
                'id' => $seller->id,
                'store_name' => $seller->store_name,
                'slug' => $seller->slug,
                'description' => $seller->description,
                'submitted_at' => $seller->created_at?->toIso8601String(),
                'owner' => [
                    'name' => $seller->user?->name,
                    'email' => $seller->user?->email,
                    'phone' => $seller->user?->phone,
                ],
            ]);

        return Inertia::render('Admin/Sellers/Approvals', [
            'applications' => $applications,
        ]);
    }

    public function approve(Seller $seller): RedirectResponse
    {
        abort_unless($seller->status === SellerStatus::Pending, 404);

        DB::transaction(function () use ($seller): void {
            $seller->update(['status' => SellerStatus::Active]);
            $seller->user?->forceFill(['is_active_as_seller' => true])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Toko :store disetujui.', ['store' => $seller->store_name])]);

        return back();
    }

    /**
     * Rejecting removes the pending application so the user can apply again.
     */
    public function reject(Seller $seller): RedirectResponse
    {
        abort_unless($seller->status === SellerStatus::Pending, 404);

        DB::transaction(function () use ($seller): void {
            $seller->user?->forceFill(['is_active_as_seller' => false])->save();
            $seller->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Pengajuan :store ditolak.', ['store' => $seller->store_name])]);

        return back();
    }
}
