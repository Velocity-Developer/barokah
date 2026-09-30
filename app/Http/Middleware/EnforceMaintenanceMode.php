<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings → General → Maintenance Mode. While it is on, shoppers get a 503
 * maintenance page; admins keep the whole site and sellers keep their
 * dashboard so paid orders still get shipped.
 */
class EnforceMaintenanceMode
{
    /** Reachable by everyone, so admins can sign in and payments can settle. */
    private const ALWAYS_OPEN = [
        'login', 'logout', 'two-factor-challenge', 'forgot-password', 'reset-password', 'reset-password/*',
        'user/confirm-password', 'robots.txt', 'up',
        'api/v1/payments/callback', 'api/v1/payments/webhook',
        // Branding and currency for the login page and the dashboards.
        'api/v1/settings/public',
    ];

    /** The seller's own dashboard area. */
    private const SELLER_AREA = [
        'dashboard', 'seller', 'seller/*', 'seller-center', 'api/v1/seller/*', 'settings', 'settings/*', 'user/*', 'payouts/*/proof',
    ];

    public function __construct(private SettingsService $settings) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isOn() || $request->is(...self::ALWAYS_OPEN) || $this->checkedLaterWithSession($request) || $this->isSessionlessRead($request)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user?->can('admin') || ($user?->can('seller') && $request->is(...self::SELLER_AREA))) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => __('The marketplace is under maintenance. Please come back soon.')], 503, ['Retry-After' => '3600']);
        }

        // An Inertia visit cannot show a plain page; send the browser to load it in full.
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        return response()->view('maintenance', ['branding' => $this->branding()], 503, ['Retry-After' => '3600']);
    }

    /**
     * API routes that also run the web group are checked again once the
     * session is started; before that the signed-in admin is still unknown.
     */
    private function checkedLaterWithSession(Request $request): bool
    {
        return ! $request->hasSession() && in_array('web', $request->route()?->gatherMiddleware() ?? [], true);
    }

    /**
     * Catalog reads have no session, so an admin browsing the store could not
     * be told apart from a shopper. Shoppers cannot reach the pages anyway;
     * writes such as checkout stay closed.
     */
    private function isSessionlessRead(Request $request): bool
    {
        return ! $request->hasSession() && $request->is('api/*') && $request->isMethodSafe();
    }

    public function isOn(): bool
    {
        return (bool) rescue(fn () => $this->settings->get('general.maintenance_mode', false), false, false);
    }

    /**
     * @return array{site_name: string, logo_url: string|null, favicon_url: string|null, primary_color: string, tagline: string|null}
     */
    private function branding(): array
    {
        $public = rescue(fn () => $this->settings->allPublic(), [], false);
        $text = fn (string $key): ?string => is_string($public[$key] ?? null) && $public[$key] !== '' ? $public[$key] : null;
        $color = $text('branding.primary_color');

        return [
            'site_name' => $text('branding.site_name') ?? config('marketplace.name', 'Barokah'),
            'logo_url' => $text('branding.logo_url'),
            'favicon_url' => $text('branding.favicon_url'),
            'primary_color' => $color !== null && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1 ? $color : '#ee4d2d',
            'tagline' => $text('general.site_tagline'),
        ];
    }
}
