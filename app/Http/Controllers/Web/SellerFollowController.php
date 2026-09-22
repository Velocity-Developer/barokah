<?php

namespace App\Http\Controllers\Web;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SellerFollowController extends Controller
{
    /**
     * Follow the seller, or unfollow when already followed.
     */
    public function toggle(Request $request, Seller $seller): RedirectResponse
    {
        abort_unless($seller->status === SellerStatus::Active, 404);

        $changes = $request->user()->followedSellers()->toggle([$seller->getKey()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes['attached'] !== []
                ? __('Berhasil mengikuti :store.', ['store' => $seller->store_name])
                : __('Berhenti mengikuti :store.', ['store' => $seller->store_name]),
        ]);

        return back();
    }
}
