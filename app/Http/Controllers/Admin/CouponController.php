<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Coupons/Index', ['coupons' => Coupon::with('seller:id,store_name')->latest()->paginate(25)->withQueryString()]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Coupons/Create');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('Admin/Coupons/Edit', ['coupon' => $coupon]);
    }
}
