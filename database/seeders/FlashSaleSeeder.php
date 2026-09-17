<?php

namespace Database\Seeders;

use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FlashSaleSeeder extends Seeder
{
    public function run(): void
    {
        $startsAt = Carbon::now()->subHour();
        $endsAt = Carbon::now()->addDays(3);
        $products = Product::query()
            ->where('status', 'active')
            ->get()
            ->groupBy('seller_id')
            ->flatMap(fn ($sellerProducts) => $sellerProducts->shuffle()->take(4))
            ->shuffle()
            ->take(12);

        foreach ($products as $product) {
            FlashSale::query()->updateOrCreate(
                ['product_id' => $product->id, 'starts_at' => $startsAt],
                [
                    'price' => round((float) $product->price * 0.8, 2),
                    'discount_type' => 'percentage',
                    'discount_value' => 20,
                    'quantity' => min(20, $product->stock),
                    'quantity_sold' => 0,
                    'ends_at' => $endsAt,
                ],
            );
        }
    }
}
