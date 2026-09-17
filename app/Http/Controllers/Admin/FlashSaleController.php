<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class FlashSaleController extends Controller
{
    public function index(): Response
    {
        $flashSales = FlashSale::query()
            ->with('product:id,name,slug,seller_id', 'product.seller:id,store_name')
            ->latest('starts_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/FlashSales/Index', ['flashSales' => $flashSales]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/FlashSales/Create', [
            'products' => Product::query()->where('status', 'active')->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function edit(FlashSale $flashSale): Response
    {
        return Inertia::render('Admin/FlashSales/Edit', ['flashSale' => $flashSale->load('product:id,name')]);
    }
}
