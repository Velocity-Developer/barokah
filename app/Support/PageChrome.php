<?php

namespace App\Support;

/**
 * Which Inertia pages wear the dashboard chrome.
 *
 * The storefront and the auth screens are always light; only the dashboard
 * follows the appearance setting. resources/js/app.ts makes the same call on
 * the client, so keep the two lists in step.
 */
class PageChrome
{
    /** Storefront pages render their own marketplace layout. */
    private const STOREFRONT = [
        'Home',
        'Welcome',
        'Product/',
        'FlashSale/',
        'Cart/',
        'Checkout/',
        'Coupon/',
        'Tracking/',
        'Help/',
        'Rating/',
        'Store/',
        'Profile/',
    ];

    public static function usesDashboard(?string $component): bool
    {
        $component = (string) $component;

        if ($component === '' || str_starts_with($component, 'auth/')) {
            return false;
        }

        foreach (self::STOREFRONT as $page) {
            if ($component === $page || str_starts_with($component, $page)) {
                return false;
            }
        }

        return true;
    }
}
