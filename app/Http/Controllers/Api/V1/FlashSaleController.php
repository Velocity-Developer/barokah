<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFlashSaleRequest;
use App\Http\Requests\Api\V1\UpdateFlashSaleRequest;
use App\Http\Resources\Api\V1\FlashSaleResource;
use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FlashSaleController extends Controller
{
    public function store(StoreFlashSaleRequest $request, Product $product): FlashSaleResource
    {
        Gate::authorize('update', $product);

        return new FlashSaleResource($this->save($request->validated(), $product));
    }

    public function update(UpdateFlashSaleRequest $request, Product $product): FlashSaleResource
    {
        Gate::authorize('update', $product);
        $sale = $product->flashSales()->latest()->firstOrFail();
        if ($sale->ends_at->lte(now())) {
            abort(422, 'Finished flash sale cannot be changed.');
        }

        return new FlashSaleResource($this->save($request->validated(), $product, $sale));
    }

    public function adminStore(StoreFlashSaleRequest $request, Product $product): FlashSaleResource
    {
        return new FlashSaleResource($this->save($request->validated(), $product));
    }

    public function adminUpdate(UpdateFlashSaleRequest $request, FlashSale $flashSale): FlashSaleResource
    {
        if ($flashSale->ends_at->lte(now())) {
            abort(422, 'Finished flash sale cannot be changed.');
        }

        return new FlashSaleResource($this->save($request->validated(), $flashSale->product, $flashSale));
    }

    public function adminDestroy(FlashSale $flashSale): Response
    {
        if ($flashSale->starts_at->lte(now())) {
            $flashSale->update(['ends_at' => now()]);
        } else {
            $flashSale->delete();
        }

        return response()->noContent();
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('update', $product);
        $sale = $product->flashSales()->latest()->firstOrFail();
        if ($sale->starts_at->lte(now())) {
            $sale->update(['ends_at' => now()]);
        } else {
            $sale->delete();
        }

        return response()->noContent();
    }

    private function save(array $data, Product $product, ?FlashSale $sale = null): FlashSale
    {
        $data = array_merge($sale?->only(['discount_type', 'discount_value', 'quantity', 'starts_at', 'ends_at']) ?? [], $data);
        $startsAt = now()->parse($data['starts_at']);
        $endsAt = now()->parse($data['ends_at']);
        $type = $data['discount_type'];
        $value = (float) $data['discount_value'];
        if ($type === 'percentage' && $value > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Percentage cannot exceed 100.']);
        }
        $price = $type === 'fixed' ? $value : (float) $product->price * (1 - $value / 100);
        if ($price <= 0 || $price >= (float) $product->price) {
            throw ValidationException::withMessages(['discount_value' => 'Promo price must be lower than normal price and greater than zero.']);
        }
        if ((int) $data['quantity'] > $product->stock + ($sale?->quantity_sold ?? 0)) {
            throw ValidationException::withMessages(['quantity' => 'Quantity exceeds available stock.']);
        }
        $overlap = $product->flashSales()->when($sale, fn ($query) => $query->where('id', '!=', $sale->id))->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Flash sale period overlaps another sale.']);
        }

        return DB::transaction(fn (): FlashSale => $product->flashSales()->updateOrCreate(['id' => $sale?->id], [
            'price' => round($price, 2), 'discount_type' => $type, 'discount_value' => $value,
            'quantity' => $data['quantity'], 'starts_at' => $startsAt, 'ends_at' => $endsAt,
        ]));
    }
}
