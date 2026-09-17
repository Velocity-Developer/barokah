<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlashSaleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $products = Product::query()
            ->active()
            ->whereHas('flashSales', fn ($query) => $query->active())
            ->with(['seller', 'category', 'images', 'flashSales'])
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('FlashSale/Index', [
            'products' => ProductResource::collection($products),
        ]);
    }
}
