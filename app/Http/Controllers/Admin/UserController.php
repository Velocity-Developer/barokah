<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\CurrencyFormatter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin Inertia shells for admin customer management (spec §17). The list
 * fetches from the admin JSON API (`/api/v1/admin/users`) so the CRUD logic
 * lives in one place; detail adds the order history shown beside the profile.
 */
class UserController extends Controller
{
    /** Orders that count as money spent (paid onwards). */
    private const PAID_STATUSES = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    private const RECENT_ORDERS = 5;

    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index');
    }

    public function show(User $user, CurrencyFormatter $currency): Response
    {
        $user->load(['seller' => fn ($query) => $query->withCount('products')]);

        $orders = Order::query()->where('user_id', $user->id);
        $spent = (float) (clone $orders)->whereIn('status', self::PAID_STATUSES)->sum('total');

        return Inertia::render('Admin/Users/Show', [
            'user' => $this->payload($user),
            'stats' => [
                'orders' => (clone $orders)->count(),
                'spent_formatted' => $currency->format($spent),
                'followed_stores' => $user->followedSellers()->count(),
                'favorite_products' => $user->favoriteProducts()->count(),
            ],
            'recent_orders' => (clone $orders)
                ->latest()
                ->limit(self::RECENT_ORDERS)
                ->get()
                ->map(fn (Order $order): array => [
                    'order_number' => $order->order_number,
                    'status' => $order->status instanceof \BackedEnum ? $order->status->value : $order->status,
                    'total_formatted' => $currency->format((float) $order->total),
                    'created_at' => $order->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->payload($user->load('seller')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'state' => $user->state,
            'city' => $user->city,
            'post_code' => $user->post_code,
            'profile_photo_url' => $user->profile_photo_url,
            'banner_url' => $user->banner_url,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'is_admin' => $user->isAdmin(),
            'is_active_as_seller' => (bool) $user->is_active_as_seller,
            'is_seller' => $user->isSeller(),
            'joined_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'seller' => $user->seller ? [
                'id' => $user->seller->id,
                'store_name' => $user->seller->store_name,
                'slug' => $user->seller->slug,
                'status' => $user->seller->status instanceof \BackedEnum ? $user->seller->status->value : $user->seller->status,
                'products_count' => $user->seller->products_count,
            ] : null,
        ];
    }
}
