<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $sellerId = $request->user()->seller()->value('id');

        return Inertia::render('Seller/Coupons/Index', ['coupons' => Coupon::where('seller_id', $sellerId)->latest()->paginate(25)->withQueryString()]);
    }

    public function create(): Response
    {
        return Inertia::render('Seller/Coupons/Create');
    }

    public function edit(Request $request, Coupon $coupon): Response
    {
        Gate::authorize('update', $coupon);

        return Inertia::render('Seller/Coupons/Edit', ['coupon' => $coupon]);
    }
}
