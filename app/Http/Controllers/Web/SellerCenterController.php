<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Seller Center" entry point: send each user to the area they can use.
 */
class SellerCenterController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->can('admin')) {
            return to_route('admin.dashboard');
        }

        if ($user->can('seller')) {
            return to_route('seller.dashboard');
        }

        return to_route('profile.show', ['tab' => 'seller']);
    }
}
