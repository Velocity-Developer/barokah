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
        // Oldest first: the store that has waited longest is reviewed first.
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
                'profile_photo_url' => $seller->displayPhotoUrl(),
                'banner_url' => $seller->displayBannerUrl(),
                'phone' => $seller->phone,
                'whatsapp' => $seller->whatsapp,
                'location' => $this->location($seller),
                'bank_account' => $seller->bank_account,
                'submitted_at' => $seller->created_at?->toIso8601String(),
                'owner' => [
                    'id' => $seller->user?->id,
                    'name' => $seller->user?->name,
                    'email' => $seller->user?->email,
                    'phone' => $seller->user?->phone,
                    'joined_at' => $seller->user?->created_at?->toIso8601String(),
                ],
            ]);

        return Inertia::render('Admin/Sellers/Approvals', [
            'applications' => $applications,
            'active_stores_count' => Seller::query()->where('status', SellerStatus::Active)->count(),
        ]);
    }

    /**
     * Store address without repeating the city / state it already contains.
     */
    private function location(Seller $seller): ?string
    {
        $address = (string) $seller->store_location;
        $extra = collect([$seller->city, $seller->state])
            ->filter(fn (?string $part): bool => $part !== null && $part !== '' && ! str_contains(mb_strtolower($address), mb_strtolower($part)));

        $location = collect([$address])->concat($extra)->filter()->implode(', ');

        return $location === '' ? null : $location;
    }

    public function approve(Seller $seller): RedirectResponse
    {
        abort_unless($seller->status === SellerStatus::Pending, 404);

        DB::transaction(function () use ($seller): void {
            $seller->update(['status' => SellerStatus::Active]);
            $seller->user?->forceFill(['is_active_as_seller' => true])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':store has been approved.', ['store' => $seller->store_name])]);

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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The application for :store was rejected.', ['store' => $seller->store_name])]);

        return back();
    }
}
