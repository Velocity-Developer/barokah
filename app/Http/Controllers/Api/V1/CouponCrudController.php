<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CouponCrudController extends Controller
{
    public function store(Request $request): Coupon
    {
        $data = $this->validated($request);
        $user = $request->user();
        if ($user->can('seller')) {
            $data['owner_type'] = 'seller';
            $data['seller_id'] = $user->seller()->value('id');
        }

        return Coupon::create($data);
    }

    public function update(Request $request, Coupon $coupon): Coupon
    {
        abort_unless($request->user()->is_admin || ($coupon->owner_type === 'seller' && $coupon->seller_id === $request->user()->seller()->value('id')), 403);
        $coupon->update($this->validated($request, $coupon));

        return $coupon->refresh();
    }

    public function destroy(Request $request, Coupon $coupon): Response
    {
        abort_unless($request->user()->is_admin || ($coupon->owner_type === 'seller' && $coupon->seller_id === $request->user()->seller()->value('id')), 403);
        $coupon->delete();

        return response()->noContent();
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
