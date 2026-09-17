<?php

namespace Database\Factories;

use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FlashSale>
 */
class FlashSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::now()->subHour();

        return [
            'product_id' => Product::factory(),
            'price' => 8,
            'discount_type' => 'fixed',
            'discount_value' => 2,
            'quantity' => 10,
            'quantity_sold' => 0,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDay(),
        ];
    }
}
