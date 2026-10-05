<?php

namespace App\Http\Middleware;

use App\Enums\PayoutStatus;
use App\Enums\SellerStatus;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Seller;
use App\Models\SellerPayout;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Same gates as the admin / seller route groups, so the header
                // only offers dashboards the user can actually open.
                'pending_seller_approvals' => fn () => $request->user()?->can('admin')
                    ? Seller::query()->where('status', SellerStatus::Pending)->count()
                    : 0,
                'pending_payouts' => fn () => $request->user()?->can('admin')
                    ? SellerPayout::query()->where('status', PayoutStatus::Pending)->count()
                    : 0,
                'unread_messages' => fn () => $request->user() !== null
                    ? Conversation::unreadTotalFor($request->user())
                    : 0,
                'can' => [
                    'admin' => $request->user()?->can('admin') ?? false,
                    'seller' => $request->user()?->can('seller') ?? false,
                ],
            ],
            // Only staff get past maintenance, so only they need the reminder bar.
            'maintenance_mode' => fn () => ($request->user()?->can('admin') || $request->user()?->can('seller'))
                && app(EnforceMaintenanceMode::class)->isOn(),
            // Storefront header and footer category links, in the order admins set.
            'nav_categories' => fn () => Category::query()
                ->active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'slug']),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
