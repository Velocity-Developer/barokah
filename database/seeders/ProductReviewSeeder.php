<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSellerTracking;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->where('email', 'like', 'reviewer%@barokah.local')->orderBy('id')->take(10)->get();
        $products = Product::query()->where('status', 'active')->get();

        foreach ($products as $product) {
            foreach ($users as $index => $user) {
                $order = Order::query()->updateOrCreate(
                    ['order_number' => sprintf('BRK-REVIEW-%03d-%03d', $product->id, $user->id)],
                    [
                        'user_id' => $user->id,
                        'customer_name' => $user->name,
                        'customer_address' => $user->address ?? 'Demo review address',
                        'customer_state' => $user->state ?? 'Selangor',
                        'customer_city' => $user->city,
                        'customer_post_code' => $user->post_code ?? '47300',
                        'customer_phone' => $user->phone ?? '018-0000000',
                        'customer_email' => $user->email,
                        'currency_code' => 'MYR',
                        'subtotal' => $product->price,
                        'shipping_fee' => '0.00',
                        'total' => $product->price,
                        'status' => OrderStatus::Completed,
                        'shipping_method' => 'fixed',
                    ],
                );

                $item = OrderItem::query()->updateOrCreate(
                    ['order_id' => $order->id, 'product_id' => $product->id],
                    [
                        'seller_id' => $product->seller_id,
                        'product_name_snapshot' => $product->name,
                        'product_slug_snapshot' => $product->slug,
                        'price_snapshot' => $product->price,
                        'quantity' => 1,
                        'subtotal' => $product->price,
                    ],
                );

                OrderSellerTracking::query()->updateOrCreate(
                    ['order_id' => $order->id, 'seller_id' => $product->seller_id],
                    [
                        'shipping_fee' => '0.00',
                        'tracking_status' => 'delivered',
                        'received_at' => now()->subDays(8),
                        'packed_at' => now()->subDays(7),
                        'picked_up_at' => now()->subDays(6),
                        'delivered_at' => now()->subDays(5),
                    ],
                );

                ProductReview::query()->updateOrCreate(
                    ['user_id' => $user->id, 'order_item_id' => $item->id],
                    [
                        'product_id' => $product->id,
                        'rating' => ($index % 5) + 1,
                        'review' => fake()->randomElement([
                            'Produk sesuai deskripsi dan kualitas bagus.',
                            'Pengiriman cepat, produk aman sampai.',
                            'Kemasan rapi dan produk memuaskan.',
                        ]),
                        'media' => [],
                    ],
                );
            }
        }
    }
}
