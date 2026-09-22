<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CouponCrudController extends Controller
{
    public function store(Request $request): Coupon
    {
        $data = $this->validated($request);
        $user = $request->user();

        if ($user->is_admin) {
            // Admins pick the scope: every store (global) or one store.
            $data = [...$data, ...$this->ownership($request)];
        } elseif ($user->can('seller')) {
            $data['owner_type'] = 'seller';
            $data['seller_id'] = $user->seller()->value('id');
        }

        return Coupon::create($data);
    }

    public function update(Request $request, Coupon $coupon): Coupon
    {
        abort_unless($request->user()->is_admin || ($coupon->owner_type === 'seller' && $coupon->seller_id === $request->user()->seller()->value('id')), 403);
        $data = $this->validated($request, $coupon);

        if ($request->user()->is_admin && $request->has('owner_type')) {
            $data = [...$data, ...$this->ownership($request)];
        }

        $coupon->update($data);

        return $coupon->refresh();
    }

    public function destroy(Request $request, Coupon $coupon): Response|JsonResponse
    {
        abort_unless($request->user()->is_admin || ($coupon->owner_type === 'seller' && $coupon->seller_id === $request->user()->seller()->value('id')), 403);
        // Usage rows feed per-customer limits and reports; deleting would cascade them away.
        if ($coupon->usage_count > 0 || $coupon->usages()->exists()) {
            return response()->json(['message' => 'This coupon has already been used. Disable it instead of deleting it.'], 422);
        }

        $coupon->delete();

        return response()->noContent();
    }

    /**
     * @return array{owner_type: string, seller_id: int|null}
     */
    private function ownership(Request $request): array
    {
        $owner = $request->validate([
            'owner_type' => ['sometimes', Rule::in(['global', 'seller'])],
            'seller_id' => ['nullable', 'required_if:owner_type,seller', 'integer', Rule::exists('sellers', 'id')],
        ]);

        $isSeller = ($owner['owner_type'] ?? 'global') === 'seller';

        return ['owner_type' => $isSeller ? 'seller' : 'global', 'seller_id' => $isSeller ? (int) $owner['seller_id'] : null];
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'uppercase', Rule::unique('coupons', 'code')->ignore($coupon)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed', 'free_shipping'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'minimum_spend' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'allow_flash_sale' => ['boolean'],
            'status' => ['boolean'],
        ]);
    }
}
