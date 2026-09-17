<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $startsAt = Carbon::now()->subDay();
        $endsAt = Carbon::now()->addDays(14);

        $globalCoupons = [
            [
                'code' => 'BAROKAH10',
                'name' => 'Diskon 10% Barokah',
                'description' => 'Diskon 10% untuk semua produk.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'maximum_discount' => 30000,
                'minimum_spend' => 100000,
                'usage_limit' => 100,
                'per_user_limit' => 1,
                'allow_flash_sale' => true,
            ],
            [
                'code' => 'HEMAT25K',
                'name' => 'Hemat RM25',
                'description' => 'Potongan tetap untuk pembelian minimum.',
                'discount_type' => 'fixed',
                'discount_value' => 25,
                'maximum_discount' => null,
                'minimum_spend' => 150,
                'usage_limit' => 75,
                'per_user_limit' => 1,
                'allow_flash_sale' => true,
            ],
            [
                'code' => 'ONGKIRGRATIS',
                'name' => 'Gratis Ongkir',
                'description' => 'Bebas biaya pengiriman.',
                'discount_type' => 'free_shipping',
                'discount_value' => 0,
                'maximum_discount' => 20,
                'minimum_spend' => 80,
                'usage_limit' => 50,
                'per_user_limit' => 1,
                'allow_flash_sale' => false,
            ],
        ];

        foreach ($globalCoupons as $coupon) {
            Coupon::query()->updateOrCreate(
                ['code' => $coupon['code']],
                array_merge($coupon, [
                    'owner_type' => 'global',
                    'seller_id' => null,
                    'usage_count' => 0,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => true,
                ]),
            );
        }

        Seller::query()
            ->whereHas('products')
            ->inRandomOrder()
            ->take(3)
            ->get()
            ->each(function (Seller $seller, int $index) use ($startsAt, $endsAt): void {
                $number = $index + 1;
                Coupon::query()->updateOrCreate(
                    ['code' => 'TOKO'.$number.'15'],
                    [
                        'name' => 'Diskon Toko '.$number,
                        'description' => 'Kupon khusus toko seller.',
                        'owner_type' => 'seller',
                        'seller_id' => $seller->id,
                        'discount_type' => 'percentage',
                        'discount_value' => 15,
                        'maximum_discount' => 20,
                        'minimum_spend' => 100,
                        'usage_limit' => 50,
                        'usage_count' => 0,
                        'per_user_limit' => 1,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'allow_flash_sale' => true,
                        'status' => true,
                    ],
                );
            });
    }
}
