import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme, setThemedPage } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { useSettingsStore } from '@/stores/settings';

void useSettingsStore().loadSettings();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/** Storefront pages bring their own marketplace layout. */
const STOREFRONT_PAGES = [
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

function isStorefront(name: string): boolean {
    return name === 'Home' || name === 'Welcome' || STOREFRONT_PAGES.some((page) => name.startsWith(page));
}

/** Mirrors App\Support\PageChrome on the server; keep the two in step. */
function usesDashboard(name: string): boolean {
    return !isStorefront(name) && !name.startsWith('auth/');
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case isStorefront(name):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// The appearance setting applies to the dashboard only, so the theme is
// re-evaluated on the first render and on every navigation.
function initialComponent(): string {
    try {
        return (JSON.parse(document.getElementById('app')?.dataset.page ?? '{}') as { component?: string }).component ?? '';
    } catch {
        return '';
    }
}

setThemedPage(usesDashboard(initialComponent()));
router.on('navigate', (event) => setThemedPage(usesDashboard(event.detail.page.component)));

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
