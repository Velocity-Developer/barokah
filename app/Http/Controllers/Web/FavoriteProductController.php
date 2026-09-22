<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FavoriteProductController extends Controller
{
    /**
     * Add the product to favorites, or remove it when already favorited.
     */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === ProductStatus::Active, 404);

        $changes = $request->user()->favoriteProducts()->toggle([$product->getKey()]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $changes['attached'] !== []
                ? __('Ditambahkan ke produk favorit.')
                : __('Dihapus dari produk favorit.'),
        ]);

        return back();
    }
}
