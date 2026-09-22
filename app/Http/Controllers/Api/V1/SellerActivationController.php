<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Seller\SubmitSellerApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ActivateSellerRequest;
use App\Http\Resources\Api\V1\SellerResource;
use Illuminate\Http\JsonResponse;

class SellerActivationController extends Controller
{
    /**
     * Submit a seller application; the store stays Pending until an admin
     * approves it.
     */
    public function __invoke(ActivateSellerRequest $request, SubmitSellerApplication $submit): SellerResource|JsonResponse
    {
        $user = $request->user();

        if ($user->seller()->exists()) {
            return response()->json([
                'message' => 'Buyer is already active as seller.',
            ], 403);
        }

        $seller = $submit->handle($user, $request->validated());

        return (new SellerResource($seller))->response()->setStatusCode(201);
    }
}
