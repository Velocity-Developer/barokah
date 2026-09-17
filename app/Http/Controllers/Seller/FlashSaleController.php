<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FlashSaleController extends Controller
{
    public function index(Request $request): Response
    {
        $sellerId = $request->user()->load('seller')->seller?->id;
        $flashSales = FlashSale::query()
            ->whereHas('product', fn ($query) => $query->where('seller_id', $sellerId))
            ->with('product:id,name,slug')
            ->latest('starts_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Seller/FlashSales/Index', ['flashSales' => $flashSales]);
    }

    public function create(Request $request): Response
    {
        $sellerId = $request->user()->load('seller')->seller?->id;

        return Inertia::render('Seller/FlashSales/Create', [
            'products' => Product::query()->where('seller_id', $sellerId)->where('status', 'active')->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, FlashSale $flashSale): Response
    {
        Gate::authorize('update', $flashSale->product);

        return Inertia::render('Seller/FlashSales/Edit', [
            'flashSale' => $flashSale->load('product:id,name'),
        ]);
    }
}
