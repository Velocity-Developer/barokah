<?php

namespace App\Http\Controllers;

use App\Models\SellerPayout;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Transfer proofs show bank details, so only admins and the paid store see them.
 */
class PayoutProofController extends Controller
{
    public function __invoke(Request $request, SellerPayout $payout): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('admin') || $user->seller?->id === $payout->seller_id, 403);

        $disk = Storage::disk(PayoutService::PROOF_DISK);
        abort_if($payout->proof_path === null || ! $disk->exists($payout->proof_path), 404);

        return $disk->response($payout->proof_path);
    }
}
