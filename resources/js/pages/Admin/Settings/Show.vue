<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import type { Component } from 'vue';
import {
    Coins,
    Cog,
    CreditCard,
    Languages,
    LayoutTemplate,
    Mail as MailIcon,
    Palette,
    Phone,
    Search,
    ShieldCheck,
    ShoppingCart,
    Store as StoreIcon,
    Truck,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/admin/settings';
import malaysiaStates from '@/data/malaysia-states.json';
import { getMalaysiaCities } from '@/composables/useMalaysiaCities';

type AdminSettingEntry = {
    key: string;
    value: string | number | boolean | string[] | null;
    type: string;
    group: string;
    is_public: boolean;
    masked: boolean;
};

type ShippingRate = {
    id: number;
    from_state: string | null;
    from_city: string | null;
    to_state: string;
    to_city: string | null;
    rate: string | number;
    is_active: boolean;
};

const stateOptions = (malaysiaStates as { name: string }[]).map((state) => state.name);

const props = defineProps<{
    groups: string[];
    activeGroup: string;
    settings: AdminSettingEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Settings',
                href: show(),
            },
        ],
    },
});

const MASKED_SENTINEL = '••••••••';

const values = reactive<Record<string, string | number | boolean | string[] | null>>(
    Object.fromEntries(props.settings.map((setting) => [setting.key, setting.value])),
);

watch(
    () => props.settings,
    (settings) => {
        Object.assign(
            values,
            Object.fromEntries(settings.map((setting) => [setting.key, setting.value])),
        );
    },
    { deep: true },
);

const errors = ref<Record<string, string>>({});
const isSaving = ref(false);

onMounted(() => {
    const bannerIndexes = props.settings
        .filter((setting) => setting.key.startsWith('homepage.banner_') && setting.value)
        .map((setting) => Number(setting.key.match(/banner_(\d+)_url/)?.[1] ?? 1));
    bannerCount.value = Math.max(1, ...bannerIndexes);
});
const logoFile = ref<File | null>(null);
const faviconFile = ref<File | null>(null);
const qrCodeFile = ref<File | null>(null);
const homepageBannerFiles = ref<Record<string, File | null>>(
    Object.fromEntries(
        Array.from({ length: 10 }, (_, index) => [`homepage_banner_${index + 1}`, null]),
    ),
);
const bannerCount = ref(1);
const closedBannerIndexes = ref<Set<number>>(new Set());
const shippingRates = ref<ShippingRate[]>([]);
const newRate = reactive({ from_state: '', from_city: '', to_state: '', to_city: '', rate: '', is_active: true });
const rateError = ref<string | null>(null);
const isRateSaving = ref(false);
const fromCityOptions = computed(() => {
    const cities = getMalaysiaCities(newRate.from_state);
    return newRate.from_state !== '' && cities.length === 0 ? [newRate.from_state] : cities;
});
const toCityOptions = computed(() => {
    const cities = getMalaysiaCities(newRate.to_state);
    return newRate.to_state !== '' && cities.length === 0 ? [newRate.to_state] : cities;
});

async function loadShippingRates(): Promise<void> {
    const response = await fetch('/api/v1/admin/shipping-rates', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (response.ok) {
        shippingRates.value = ((await response.json()) as { data: ShippingRate[] }).data;
    }
}

async function addShippingRate(): Promise<void> {
    isRateSaving.value = true;
    rateError.value = null;

    try {
        const response = await fetch('/api/v1/admin/shipping-rates', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
            },
            body: JSON.stringify({
                ...newRate,
                from_state: newRate.from_state || null,
                from_city: newRate.from_city || null,
                to_city: newRate.to_city || null,
                rate: Number(newRate.rate),
                is_active: Boolean(newRate.is_active),
            }),
        });

        if (!response.ok) {
            const payload = (await response.json()) as { message?: string };
            rateError.value = payload.message ?? 'Shipping rate could not be saved.';
            return;
        }

        Object.assign(newRate, {
            from_state: '',
            from_city: '',
            to_state: '',
            to_city: '',
            rate: '',
            is_active: true,
        });
        await loadShippingRates();
    } finally {
        isRateSaving.value = false;
    }
}

async function removeShippingRate(id: number): Promise<void> {
    await fetch(`/api/v1/admin/shipping-rates/${id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
        },
    });
    await loadShippingRates();
}

watch(
    () => newRate.from_state,
    (state) => {
        const cities = getMalaysiaCities(state);
        newRate.from_city = state !== '' && cities.length === 0 ? state : '';
    },
);

watch(
    () => newRate.to_state,
    (state) => {
        const cities = getMalaysiaCities(state);
        newRate.to_city = state !== '' && cities.length === 0 ? state : '';
    },
);

watch(
    () => props.activeGroup,
    (group) => {
        if (group === 'shipping') {
            void loadShippingRates();
        }
    },
    { immediate: true },
);

function onBrandingFile(field: 'logo' | 'favicon', event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (field === 'logo') {
        logoFile.value = file;
    } else {
        faviconFile.value = file;
    }
}

function onQrCodeFile(event: Event): void {
    qrCodeFile.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function onHomepageBannerFile(key: string, event: Event): void {
    const fileKey = key.replace('_url', '').replaceAll('.', '_');

    homepageBannerFiles.value[fileKey] =
        (event.target as HTMLInputElement).files?.[0] ?? null;
}

function onHomepageSideBannerFile(key: string, event: Event): void {
    const field = key === 'homepage.right_top_banner_url'
        ? 'homepage_right_top_banner'
        : 'homepage_right_bottom_banner';

    homepageBannerFiles.value[field] =
        (event.target as HTMLInputElement).files?.[0] ?? null;
}

function bannerIndex(key: string): number {
    return Number(key.match(/banner_(\d+)_url/)?.[1] ?? 1);
}

function removeHomepageBanner(key: string): void {
    values[key] = null;
    homepageBannerFiles.value[`homepage_banner_${bannerIndex(key)}`] = null;
}

function closeHomepageBanner(key: string): void {
    const index = bannerIndex(key);

    removeHomepageBanner(key);
    closedBannerIndexes.value = new Set([...closedBannerIndexes.value, index]);

    if (index === bannerCount.value) {
        bannerCount.value = Math.max(1, index - 1);
    }
}

function addHomepageBanner(): void {
    const nextIndex = bannerCount.value + 1;

    closedBannerIndexes.value.delete(nextIndex);
    closedBannerIndexes.value = new Set(closedBannerIndexes.value);
    bannerCount.value = nextIndex;
}

const groupLabels: Record<string, string> = {
    general: 'General',
    branding: 'Branding',
    currency: 'Currency',
    marketplace: 'Marketplace',
    checkout: 'Checkout',
    payment: 'Payment',
    shipping: 'Shipping',
    localization: 'Localization',
    contact: 'Contact',
    seo: 'SEO',
    email: 'Email',
    homepage: 'Homepage',
    security: 'Security',
};

const settingLabels: Record<string, string> = {
    'payment.bank_transfer_enabled': 'Bank Transfer',
    'payment.bank_name': 'Bank Name',
    'payment.bank_account_name': 'Account Holder Name',
    'payment.bank_account_number': 'Account Number',
    'payment.qr_code_enabled': 'QR Code',
    'payment.qr_code_url': 'QR Code Image',
    'payment.paynet_enabled': 'Payment Gateway (PayNet)',
    'branding.logo_url': 'Website Logo',
    'branding.favicon_url': 'Website Favicon',
    'homepage.banner_speed': 'Banner Slider Speed (milliseconds)',
    'homepage.right_top_banner_link': 'Right Top Banner Promo Link',
    'homepage.right_bottom_banner_link': 'Right Bottom Banner Promo Link',
    'homepage.right_top_banner_url': 'Right Top Banner',
    'homepage.right_bottom_banner_url': 'Right Bottom Banner',
    'branding.site_name': 'Website Name',
    'branding.primary_color': 'Primary Color',
    'branding.primary_hover_color': 'Primary Hover Color',
    'branding.primary_soft_color': 'Primary Soft Color',
    'branding.secondary_color': 'Secondary Color',
    'general.site_tagline': 'Website Tagline',
    'general.maintenance_mode': 'Maintenance Mode',
    'general.allow_registration': 'Allow User Registration',
    'currency.code': 'Currency Code',
    'currency.symbol': 'Currency Symbol',
    'currency.decimals': 'Decimal Places',
    'currency.thousands_separator': 'Thousands Separator',
    'currency.decimal_separator': 'Decimal Separator',
    'marketplace.name': 'Marketplace Name',
    'marketplace.description': 'Marketplace Description',
    'marketplace.status': 'Marketplace Status',
    'marketplace.seller_registration_enabled': 'Allow Seller Registration',
    'marketplace.reviews_enabled': 'Enable Product Reviews',
    'marketplace.inventory_tracking_enabled': 'Enable Inventory Tracking',
    'checkout.order_expiration_minutes': 'Order Payment Expiration (Minutes)',
    'checkout.min_order_amount': 'Minimum Order Amount',
    'checkout.max_order_amount': 'Maximum Order Amount',
    'checkout.guest_checkout_enabled': 'Allow Guest Checkout',
    'payment.fpx_enabled': 'FPX',
    'payment.duitnow_enabled': 'DuitNow',
    'payment.gateway': 'Payment Gateway Name',
    'payment.merchant_id': 'Merchant ID',
    'payment.secret_key': 'Secret Key',
    'payment.sandbox_enabled': 'Sandbox Mode',
    'payment.api_base': 'Payment Gateway API URL',
    'shipping.method': 'Shipping Method',
    'shipping.fixed_rate': 'Fixed Shipping Rate',
    'shipping.free_shipping_enabled': 'Enable Free Shipping',
    'shipping.free_shipping_threshold': 'Free Shipping Minimum Amount',
    'shipping.provider_name': 'Shipping Provider Name',
    'shipping.api_enabled': 'Enable Shipping API',
    'shipping.api_key': 'Shipping API Key',
    'shipping.api_secret': 'Shipping API Secret',
    'shipping.api_base_url': 'Shipping API URL',
    'localization.default_language': 'Default Language',
    'localization.available_languages': 'Available Languages',
    'localization.timezone': 'Time Zone',
    'localization.date_format': 'Date Format',
    'localization.time_format': 'Time Format',
    'localization.gtranslate_enabled': 'Enable Google Translate',
    'contact.email': 'Contact Email',
    'contact.phone': 'Contact Phone Number',
    'contact.address': 'Contact Address',
    'contact.business_hours': 'Business Hours',
    'contact.whatsapp': 'Contact WhatsApp Number',
    'seo.meta_title': 'SEO Meta Title',
    'seo.meta_description': 'SEO Meta Description',
    'seo.keywords': 'SEO Keywords',
    'seo.og_image': 'Open Graph Image',
    'email.from_name': 'Email Sender Name',
    'email.from_address': 'Email Sender Address',
    'email.smtp_host': 'SMTP Host',
    'email.smtp_port': 'SMTP Port',
    'email.smtp_username': 'SMTP Username',
    'email.smtp_password': 'SMTP Password',
    'email.smtp_encryption': 'SMTP Encryption',
    'email.smtp_enabled': 'Use SMTP Server',
    'email.admin_notifications_enabled': 'Notify Admin of New Orders',
    'email.admin_notification_recipients': 'Admin Notification Email',
    'email.customer_notifications_enabled': 'Send Order Email to Buyer',
    'email.seller_notifications_enabled': 'Tell Sellers When an Order Is Paid',
    'security.recaptcha_enabled': 'Use Google reCAPTCHA',
    'security.recaptcha_version': 'reCAPTCHA Version',
    'security.recaptcha_site_key': 'Site Key',
    'security.recaptcha_secret_key': 'Secret Key',
    'security.recaptcha_on_login': 'Protect the Login Page',
    'security.recaptcha_on_guest_checkout': 'Protect Guest Checkout',
    'security.recaptcha_score_threshold': 'Minimum Score (v3)',
};

const groupDescriptions: Record<string, string> = {
    general: 'Site name, registration and maintenance mode.',
    branding: 'Logo, favicon and the marketplace colours.',
    homepage: 'Banners and sliders shown on the Home page.',
    currency: 'Currency symbol, code and how prices are written.',
    marketplace: 'How the marketplace itself is presented.',
    checkout: 'Order limits and what buyers must fill in.',
    payment: 'Bank transfer, QR payment and the PayNet gateway.',
    shipping: 'Shipping method, rates and the courier API.',
    localization: 'Languages, time zone and date formats.',
    contact: 'The addresses and numbers shown to customers.',
    seo: 'Titles, description and the share image.',
    email: 'Sender details, order notifications and SMTP.',
    security: 'Google reCAPTCHA on the login page and guest checkout.',
};

const groupIcons: Record<string, Component> = {
    general: Cog,
    branding: Palette,
    homepage: LayoutTemplate,
    currency: Coins,
    marketplace: StoreIcon,
    checkout: ShoppingCart,
    payment: CreditCard,
    shipping: Truck,
    localization: Languages,
    contact: Phone,
    seo: Search,
    email: MailIcon,
    security: ShieldCheck,
};

// Grouped so the left menu reads as sections instead of one long list.
const groupSections = computed(() => [
    { title: 'Site', groups: ['general', 'branding', 'homepage', 'seo'] },
    { title: 'Selling', groups: ['marketplace', 'currency', 'checkout', 'payment', 'shipping'] },
    { title: 'Communication', groups: ['contact', 'email', 'localization'] },
    { title: 'System', groups: ['security'] },
].map((section) => ({ ...section, groups: section.groups.filter((group) => props.groups.includes(group)) })));

const groupLabel = computed(() => groupLabels[props.activeGroup] ?? props.activeGroup);

type SettingRow = { setting: AdminSettingEntry; depth: 0 | 1 };

/**
 * Fields that only matter when another setting enables them. They are hidden
 * (but keep their saved value) until the parent is switched on.
 */
const dependentSettings: Record<string, { parent: string; visible: () => boolean }> = {
    'payment.bank_name': { parent: 'payment.bank_transfer_enabled', visible: () => Boolean(values['payment.bank_transfer_enabled']) },
    'payment.bank_account_name': { parent: 'payment.bank_transfer_enabled', visible: () => Boolean(values['payment.bank_transfer_enabled']) },
    'payment.bank_account_number': { parent: 'payment.bank_transfer_enabled', visible: () => Boolean(values['payment.bank_transfer_enabled']) },
    'payment.qr_code_url': { parent: 'payment.qr_code_enabled', visible: () => Boolean(values['payment.qr_code_enabled']) },
    ...Object.fromEntries(
        ['payment.gateway', 'payment.api_base', 'payment.merchant_id', 'payment.secret_key', 'payment.sandbox_enabled', 'payment.fpx_enabled', 'payment.duitnow_enabled'].map((key) => [
            key,
            { parent: 'payment.paynet_enabled', visible: () => Boolean(values['payment.paynet_enabled']) },
        ]),
    ),
    'shipping.fixed_rate': { parent: 'shipping.method', visible: () => values['shipping.method'] === 'fixed' },
    'shipping.free_shipping_threshold': { parent: 'shipping.free_shipping_enabled', visible: () => Boolean(values['shipping.free_shipping_enabled']) },
    ...Object.fromEntries(
        ['security.recaptcha_version', 'security.recaptcha_site_key', 'security.recaptcha_secret_key', 'security.recaptcha_on_login', 'security.recaptcha_on_guest_checkout'].map((key) => [
            key,
            { parent: 'security.recaptcha_enabled', visible: () => Boolean(values['security.recaptcha_enabled']) },
        ]),
    ),
    'security.recaptcha_score_threshold': {
        parent: 'security.recaptcha_enabled',
        visible: () => Boolean(values['security.recaptcha_enabled']) && values['security.recaptcha_version'] === 'v3',
    },
    'email.admin_notification_recipients': {
        parent: 'email.admin_notifications_enabled',
        visible: () => Boolean(values['email.admin_notifications_enabled']),
    },
    ...Object.fromEntries(
        ['email.smtp_host', 'email.smtp_port', 'email.smtp_encryption', 'email.smtp_username', 'email.smtp_password'].map((key) => [
            key,
            { parent: 'email.smtp_enabled', visible: () => Boolean(values['email.smtp_enabled']) },
        ]),
    ),
    ...Object.fromEntries(
        ['shipping.provider_name', 'shipping.api_base_url', 'shipping.api_key', 'shipping.api_secret'].map((key) => [
            key,
            { parent: 'shipping.api_enabled', visible: () => Boolean(values['shipping.api_enabled']) },
        ]),
    ),
};

const groupOrder: Record<string, string[]> = {
    payment: [
        'payment.bank_transfer_enabled',
        'payment.bank_name',
        'payment.bank_account_name',
        'payment.bank_account_number',
        'payment.qr_code_enabled',
        'payment.qr_code_url',
        'payment.paynet_enabled',
        'payment.gateway',
        'payment.api_base',
        'payment.merchant_id',
        'payment.secret_key',
        'payment.sandbox_enabled',
        'payment.fpx_enabled',
        'payment.duitnow_enabled',
    ],
    localization: [
        'localization.default_language',
        'localization.available_languages',
        'localization.timezone',
        'localization.date_format',
        'localization.time_format',
        'localization.gtranslate_enabled',
    ],
    security: [
        'security.recaptcha_enabled',
        'security.recaptcha_version',
        'security.recaptcha_site_key',
        'security.recaptcha_secret_key',
        'security.recaptcha_score_threshold',
        'security.recaptcha_on_login',
        'security.recaptcha_on_guest_checkout',
    ],
    email: [
        'email.from_name',
        'email.from_address',
        'email.admin_notifications_enabled',
        'email.admin_notification_recipients',
        'email.customer_notifications_enabled',
        'email.seller_notifications_enabled',
        'email.smtp_enabled',
        'email.smtp_host',
        'email.smtp_port',
        'email.smtp_encryption',
        'email.smtp_username',
        'email.smtp_password',
    ],
    shipping: [
        'shipping.method',
        'shipping.fixed_rate',
        'shipping.free_shipping_enabled',
        'shipping.free_shipping_threshold',
        'shipping.api_enabled',
        'shipping.provider_name',
        'shipping.api_base_url',
        'shipping.api_key',
        'shipping.api_secret',
    ],
};

const settingRows = computed<SettingRow[]>(() => {
    const order = groupOrder[props.activeGroup] ?? [];
    const rank = (key: string): number => (order.includes(key) ? order.indexOf(key) : order.length);
    const sorted = [...props.settings].sort((first, second) => rank(first.key) - rank(second.key));
    const keys = new Set(sorted.map((setting) => setting.key));
    const rows: SettingRow[] = [];

    for (const setting of sorted) {
        const dependency = dependentSettings[setting.key];

        if (dependency && keys.has(dependency.parent)) {
            continue;
        }

        rows.push({ setting, depth: 0 });

        for (const child of sorted) {
            const childDependency = dependentSettings[child.key];

            if (childDependency?.parent === setting.key && childDependency.visible()) {
                rows.push({ setting: child, depth: 1 });
            }
        }
    }

    return rows;
});

const selectOptions: Record<string, { value: string; label: string }[]> = {
    'security.recaptcha_version': [
        { value: 'v2', label: 'v2 — tick box' },
        { value: 'v3', label: 'v3 — score, no tick box' },
    ],
    'marketplace.status': [
        { value: 'open', label: 'Open' },
        { value: 'closed', label: 'Closed' },
        { value: 'maintenance', label: 'Maintenance' },
    ],
    'shipping.method': [
        { value: 'fixed', label: 'Fixed rate' },
        { value: 'external', label: 'External provider (API)' },
    ],
    'localization.default_language': [
        { value: 'en', label: 'English' },
        { value: 'ms', label: 'Malay' },
    ],
    'email.smtp_encryption': [
        { value: '', label: 'None' },
        { value: 'tls', label: 'TLS' },
        { value: 'ssl', label: 'SSL' },
        { value: 'starttls', label: 'STARTTLS' },
    ],
};

const languageOptions = [
    { value: 'en', label: 'English' },
    { value: 'ms', label: 'Malay' },
];

const numberRules: Record<string, { min: number; max?: number; step: number }> = {
    'currency.decimals': { min: 0, max: 4, step: 1 },
    'checkout.order_expiration_minutes': { min: 1, max: 1440, step: 1 },
    'email.smtp_port': { min: 1, max: 65535, step: 1 },
};

/** Amounts are stored as strings ("5.00") but edited as numbers. */
const amountKeys = new Set([
    'checkout.min_order_amount',
    'checkout.max_order_amount',
    'shipping.fixed_rate',
    'shipping.free_shipping_threshold',
]);

const secretKeys = new Set(['payment.secret_key', 'shipping.api_key', 'shipping.api_secret', 'email.smtp_password', 'security.recaptcha_secret_key']);

const longTextKeys = new Set(['marketplace.description', 'seo.meta_description', 'contact.address']);

const settingHints: Record<string, string> = {
    'shipping.fixed_rate': 'Charged per order when the shipping method is Fixed rate.',
    'shipping.free_shipping_threshold': 'Orders at or above this amount ship for free.',
    'checkout.max_order_amount': 'Leave empty for no maximum.',
    'payment.sandbox_enabled': 'Use the PayNet test environment instead of live payments.',
    'general.maintenance_mode': 'Visitors see a maintenance page while this is on.',
    'localization.available_languages': 'Languages visitors can switch to.',
    'email.from_address': 'Order emails are sent from this address.',
    'email.smtp_enabled': 'Send email through the SMTP server below. When off, the server\'s default mailer is used.',
    'email.admin_notifications_enabled': 'Send an email to the marketplace team whenever an order is placed.',
    'email.admin_notification_recipients': 'Where order notifications go. Separate several addresses with commas. Leave empty to use the admin accounts, then the contact email.',
    'email.customer_notifications_enabled': 'Send the buyer an order confirmation email.',
    'email.seller_notifications_enabled': 'Email each store when an order containing its products has been paid, so it can pack and send.',
    'security.recaptcha_enabled': 'Get the keys from google.com/recaptcha. Nothing is checked until both keys are filled in.',
    'security.recaptcha_version': 'v2 shows the "I am not a robot" tick box. v3 runs in the background and scores the visitor.',
    'security.recaptcha_site_key': 'Public key used by the page.',
    'security.recaptcha_secret_key': 'Server-side key. It is never sent to the browser.',
    'security.recaptcha_on_login': 'Ask for the captcha before checking the password.',
    'security.recaptcha_on_guest_checkout': 'Ask shoppers who are not signed in for the captcha when they place an order.',
    'security.recaptcha_score_threshold': '0 lets everyone through, 1 is strictest. 0.5 suits most sites.',
};

const settingPlaceholders: Record<string, string> = {
    'security.recaptcha_site_key': '6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'security.recaptcha_score_threshold': '0.5',
    'email.admin_notification_recipients': 'orders@example.com, owner@example.com',
};

function clearMaskedValue(setting: AdminSettingEntry): void {
    if (setting.masked && values[setting.key] === MASKED_SENTINEL) {
        values[setting.key] = '';
    }
}

function restoreMaskedValue(setting: AdminSettingEntry): void {
    if (setting.masked && values[setting.key] === '') {
        values[setting.key] = MASKED_SENTINEL;
    }
}

function languageSelected(key: string, language: string): boolean {
    const current = values[key];

    return Array.isArray(current) && current.includes(language);
}

function toggleLanguage(key: string, language: string, checked: boolean): void {
    const current = Array.isArray(values[key]) ? [...(values[key] as string[])] : [];
    values[key] = checked ? [...new Set([...current, language])] : current.filter((item) => item !== language);
}

function settingLabel(key: string): string {
    if (settingLabels[key]) {
        return settingLabels[key];
    }

    const name = key.split('.').at(-1) ?? key;

    return name
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function inputKind(type: string): 'checkbox' | 'color' | 'number' | 'text' {
    if (type === 'boolean') {
        return 'checkbox';
    }

    if (type === 'color') {
        return 'color';
    }

    if (type === 'integer' || type === 'number') {
        return 'number';
    }

    return 'text';
}

function onSettingsSubmit(event: SubmitEvent): void {
    event.preventDefault();
    void save();
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    try {
        const formData = new FormData();
        formData.append('settings', JSON.stringify(props.settings.map((setting) => ({
            key: setting.key,
            value: values[setting.key] ?? null,
        }))));
        if (logoFile.value) formData.append('branding_logo', logoFile.value);
        if (faviconFile.value) formData.append('branding_favicon', faviconFile.value);
        if (qrCodeFile.value) formData.append('payment_qr_code', qrCodeFile.value);
        Object.entries(homepageBannerFiles.value).forEach(([key, file]) => {
            if (file) {
                formData.append(key.replaceAll('.', '_'), file);
            }
        });

        const response = await fetch('/api/v1/admin/settings', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
                        ?.content ?? '',
            },
            body: formData,
        });

        const responseText = await response.text();
        let payload: { errors?: Record<string, string[]>; message?: string } = {};

        try {
            payload = JSON.parse(responseText || '{}') as typeof payload;
        } catch {
            toast.error(`Settings could not be saved (${response.status}).`);
            return;
        }

        if (!response.ok) {
            const first: Record<string, string> = {};

            for (const [field, messages] of Object.entries(payload.errors ?? {})) {
                first[field] = messages[0] ?? 'Invalid value.';
            }

            errors.value = first;
            toast.error(payload.message ?? 'Settings could not be saved.');
            return;
        }

        toast.success(`${groupLabel.value} settings saved.`);
        router.reload({ only: ['settings'] });
    } catch {
        toast.error('Settings are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head title="Settings" />

    <div class="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading variant="small" title="Settings" description="Everything that configures the marketplace. Saved values apply right away; secrets stay hidden." />

        <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
            <!-- Submenu: sections on the left, the chosen group on the right. -->
            <nav class="grid content-start gap-4 self-start rounded-xl border bg-card p-3 shadow-sm md:sticky md:top-4" aria-label="Settings groups">
                <div v-for="section in groupSections" :key="section.title" class="grid gap-1">
                    <p class="px-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ section.title }}</p>
                    <Link
                        v-for="group in section.groups"
                        :key="group"
                        :href="show(group)"
                        class="flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors"
                        :class="group === activeGroup ? 'bg-muted font-medium text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'"
                        :aria-current="group === activeGroup ? 'page' : undefined"
                    >
                        <component :is="groupIcons[group]" v-if="groupIcons[group]" class="size-4 shrink-0" aria-hidden="true" />
                        {{ groupLabels[group] ?? group }}
                    </Link>
                </div>
            </nav>

        <div class="rounded-xl border bg-card p-4 shadow-sm md:p-6">
            <div class="mb-5 border-b pb-4">
                <h2 class="text-base font-medium">{{ groupLabel }}</h2>
                <p v-if="groupDescriptions[activeGroup]" class="text-sm text-muted-foreground">{{ groupDescriptions[activeGroup] }}</p>
            </div>

            <p
                v-if="settings.length === 0"
                class="text-muted-foreground text-sm"
            >
                No settings are available in this group yet.
            </p>

            <template v-if="activeGroup === 'shipping'">
                <div class="mb-6 rounded-lg border p-4">
                    <h4 class="mb-4 font-medium">Add New Shipping Rate</h4>
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="grid content-start gap-2">
                            <Label for="shipping-rate-from-state">From State</Label>
                            <select id="shipping-rate-from-state" v-model="newRate.from_state" class="h-10 rounded-md border bg-background px-3 text-sm">
                                <option value="">Select state</option>
                                <option v-for="state in stateOptions" :key="state" :value="state">{{ state }}</option>
                            </select>
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="shipping-rate-from-city">From City</Label>
                            <select id="shipping-rate-from-city" v-model="newRate.from_city" :disabled="!newRate.from_state" class="h-10 rounded-md border bg-background px-3 text-sm">
                                <option value="">Select city</option>
                                <option v-for="city in fromCityOptions" :key="city" :value="city">{{ city }}</option>
                            </select>
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="shipping-rate-to-state">To State</Label>
                            <select id="shipping-rate-to-state" v-model="newRate.to_state" class="h-10 rounded-md border bg-background px-3 text-sm">
                                <option value="">Select state</option>
                                <option v-for="state in stateOptions" :key="state" :value="state">{{ state }}</option>
                            </select>
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="shipping-rate-to-city">To City</Label>
                            <select id="shipping-rate-to-city" v-model="newRate.to_city" :disabled="!newRate.to_state" class="h-10 rounded-md border bg-background px-3 text-sm">
                                <option value="">Select city</option>
                                <option v-for="city in toCityOptions" :key="city" :value="city">{{ city }}</option>
                            </select>
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="shipping-rate-amount">Rate</Label>
                            <Input id="shipping-rate-amount" v-model="newRate.rate" type="number" min="0" step="0.01" placeholder="5.00" />
                        </div>
                        <div class="flex items-end gap-3">
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    class="h-5 w-5"
                                    :checked="newRate.is_active"
                                    @change="newRate.is_active = ($event.target as HTMLInputElement).checked"
                                />
                                Active
                            </label>
                            <Button type="button" :disabled="isRateSaving || !newRate.to_state || !newRate.to_city || !newRate.rate" @click="addShippingRate">
                                Add New Shipping Rate
                            </Button>
                        </div>
                    </div>
                    <p v-if="rateError" class="mt-3 text-sm text-destructive">{{ rateError }}</p>
                </div>

                <div v-if="shippingRates.length > 0" class="mb-6 overflow-x-auto rounded-lg border">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/50">
                            <tr>
                                <th class="p-3">From</th>
                                <th class="p-3">To</th>
                                <th class="p-3">Rate</th>
                                <th class="p-3">Status</th>
                                <th class="p-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rate in shippingRates" :key="rate.id" class="border-b last:border-0">
                                <td class="p-3">{{ rate.from_state ? `${rate.from_state} / ${rate.from_city ?? rate.from_state}` : 'All origins' }}</td>
                                <td class="p-3">{{ rate.to_state }} / {{ rate.to_city ?? rate.to_state }}</td>
                                <td class="p-3">{{ rate.rate }}</td>
                                <td class="p-3">{{ rate.is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="p-3 text-right">
                                    <Button type="button" variant="destructive" size="sm" @click="removeShippingRate(rate.id)">Delete</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <form v-if="settings.length > 0" class="space-y-6" @submit.prevent="onSettingsSubmit">
                <div v-if="activeGroup === 'homepage'" class="rounded border p-4">
                    <p class="font-medium">Homepage banners</p>
                    <p class="text-sm text-muted-foreground">Manage the banners shown on the Home page.</p>
                </div>

                <template v-if="activeGroup === 'homepage'">
                    <div class="grid content-start gap-4 rounded-lg border p-4">
                        <div>
                            <h4 class="font-medium">Primary banners</h4>
                            <p class="text-sm text-muted-foreground">Main slider banners on the Home page.</p>
                        </div>
                        <div
                            v-for="setting in settings.filter((setting) => /^homepage\.banner_\d+_url$/.test(setting.key))"
                            v-show="bannerIndex(setting.key) <= bannerCount && !closedBannerIndexes.has(bannerIndex(setting.key))"
                            :key="setting.key"
                            class="grid content-start gap-2 rounded-lg border-b pb-5 last:border-b-0"
                        >
                            <Label :for="setting.key">{{ settingLabel(setting.key) }}</Label>
                            <img v-if="values[setting.key]" :src="String(values[setting.key])" alt="Homepage banner preview" class="h-24 w-full rounded border object-cover" />
                            <Input :id="setting.key" :key="`${setting.key}-${closedBannerIndexes.has(bannerIndex(setting.key))}`" type="file" accept="image/png,image/jpeg,image/webp" @change="onHomepageBannerFile(setting.key, $event)" />
                            <div class="flex gap-2">
                                <Button type="button" variant="destructive" size="sm" @click="removeHomepageBanner(setting.key)">Remove banner</Button>
                                <Button v-if="bannerIndex(setting.key) > 1" type="button" variant="outline" size="sm" @click="closeHomepageBanner(setting.key)">Close</Button>
                            </div>
                            <p class="text-muted-foreground text-xs">PNG, JPG or WebP. Max 4 MB.</p>
                            <Input
                                :id="`${setting.key}-link`"
                                :model-value="String(values[`homepage.banner_${bannerIndex(setting.key)}_link`] ?? '')"
                                placeholder="https://example.com/promo"
                                @update:model-value="values[`homepage.banner_${bannerIndex(setting.key)}_link`] = $event"
                            />
                            <InputError class="mt-2" :message="errors[`settings.${setting.key}`]" />
                        </div>
                        <div class="flex justify-center border-t pt-4">
                            <Button type="button" variant="outline" :disabled="bannerCount >= 10" @click="addHomepageBanner">Add banner</Button>
                        </div>
                    </div>

                    <div class="grid content-start gap-2 rounded-lg border p-4">
                        <Label for="homepage.banner_speed">{{ settingLabel('homepage.banner_speed') }}</Label>
                        <Input
                            id="homepage.banner_speed"
                            type="number"
                            min="1000"
                            step="100"
                            :model-value="String(values['homepage.banner_speed'] ?? '')"
                            @update:model-value="values['homepage.banner_speed'] = String($event)"
                        />
                        <InputError class="mt-2" :message="errors['settings.homepage.banner_speed']" />
                    </div>

                    <div class="grid content-start gap-4 rounded-lg border p-4">
                        <div>
                            <h4 class="font-medium">Right banners</h4>
                            <p class="text-sm text-muted-foreground">Banners on the right side of the Home page slider.</p>
                        </div>
                        <div
                            v-for="setting in settings.filter((setting) => setting.key === 'homepage.right_top_banner_url' || setting.key === 'homepage.right_bottom_banner_url')"
                            :key="setting.key"
                            class="grid content-start gap-2 rounded-lg border-b pb-5 last:border-b-0"
                        >
                            <Label :for="setting.key">{{ settingLabel(setting.key) }}</Label>
                            <img v-if="values[setting.key]" :src="String(values[setting.key])" alt="Homepage side banner preview" class="h-24 w-full rounded border object-cover" />
                            <Input :id="setting.key" type="file" accept="image/png,image/jpeg,image/webp" @change="onHomepageSideBannerFile(setting.key, $event)" />
                            <p class="text-muted-foreground text-xs">PNG, JPG or WebP. Max 4 MB.</p>
                            <Input
                                :id="`${setting.key}-link`"
                                :model-value="String(values[setting.key.replace('_url', '_link')] ?? '')"
                                placeholder="https://example.com/promo"
                                @update:model-value="values[setting.key.replace('_url', '_link')] = $event"
                            />
                            <InputError class="mt-2" :message="errors[`settings.${setting.key.replace('_url', '_link')}`]" />
                        </div>
                    </div>
                </template>

                <div v-else class="divide-y rounded-lg border">
                    <div
                        v-for="{ setting, depth } in settingRows"
                        :key="setting.key"
                        class="grid gap-2 px-4 py-4 md:grid-cols-[260px_1fr] md:gap-6"
                        :class="depth === 1 ? 'bg-muted/30 md:pl-10' : ''"
                    >
                        <div class="md:pt-2">
                            <Label
                                :for="setting.key"
                                :class="depth === 1 ? 'border-l-2 pl-3' : ''"
                            >
                                {{ settingLabel(setting.key) }}
                            </Label>
                            <p v-if="settingHints[setting.key]" class="text-muted-foreground mt-1 text-xs" :class="depth === 1 ? 'pl-3.5' : ''">
                                {{ settingHints[setting.key] }}
                            </p>
                        </div>

                        <div class="grid content-start gap-2">
                            <!-- Masked values arrive as a placeholder; an untouched field keeps the stored value. -->
                            <Input
                                v-if="setting.masked"
                                :id="setting.key"
                                :type="secretKeys.has(setting.key) ? 'password' : 'text'"
                                autocomplete="new-password"
                                :model-value="String(values[setting.key] ?? '')"
                                @focus="clearMaskedValue(setting)"
                                @blur="restoreMaskedValue(setting)"
                                @update:model-value="values[setting.key] = $event"
                            />

                            <label
                                v-else-if="inputKind(setting.type) === 'checkbox'"
                                :for="setting.key"
                                class="flex w-fit cursor-pointer items-center gap-3 md:pt-2"
                            >
                                <input
                                    :id="setting.key"
                                    type="checkbox"
                                    class="h-5 w-5 cursor-pointer accent-[var(--brand-primary,#ee4d2d)]"
                                    :checked="Boolean(values[setting.key])"
                                    @change="values[setting.key] = ($event.target as HTMLInputElement).checked"
                                />
                                <span class="text-sm">{{ values[setting.key] ? 'Enabled' : 'Disabled' }}</span>
                            </label>

                            <div v-else-if="inputKind(setting.type) === 'color'" class="flex items-center gap-3">
                                <Input
                                    :id="setting.key"
                                    type="color"
                                    class="h-10 w-16 cursor-pointer p-1"
                                    :model-value="String(values[setting.key] ?? '#000000')"
                                    @update:model-value="values[setting.key] = $event"
                                />
                                <Input
                                    class="w-32 font-mono"
                                    :model-value="String(values[setting.key] ?? '')"
                                    aria-label="Hex color"
                                    @update:model-value="values[setting.key] = $event"
                                />
                            </div>

                            <template v-else-if="setting.key === 'branding.logo_url'">
                                <img v-if="values[setting.key]" :src="String(values[setting.key])" alt="Logo preview" class="h-16 max-w-48 rounded border object-contain p-2" />
                                <Input :id="setting.key" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" @change="onBrandingFile('logo', $event)" />
                                <p class="text-muted-foreground text-xs">PNG, JPG, WebP or SVG. Max 2 MB.</p>
                            </template>
                            <template v-else-if="setting.key === 'branding.favicon_url'">
                                <img v-if="values[setting.key]" :src="String(values[setting.key])" alt="Favicon preview" class="h-12 w-12 rounded border object-contain p-2" />
                                <Input :id="setting.key" type="file" accept="image/png,image/jpeg,image/webp,image/x-icon,image/svg+xml" @change="onBrandingFile('favicon', $event)" />
                                <p class="text-muted-foreground text-xs">PNG, JPG, WebP, ICO or SVG. Max 1 MB.</p>
                            </template>
                            <template v-else-if="setting.key === 'payment.qr_code_url'">
                                <img v-if="values[setting.key]" :src="String(values[setting.key])" alt="QR code preview" class="h-48 w-48 rounded border object-contain p-2" />
                                <Input :id="setting.key" type="file" accept="image/png,image/jpeg,image/webp" @change="onQrCodeFile" />
                                <p class="text-muted-foreground text-xs">PNG, JPG or WebP. Max 2 MB.</p>
                            </template>

                            <select
                                v-else-if="selectOptions[setting.key]"
                                :id="setting.key"
                                class="h-10 w-full max-w-sm rounded-md border bg-background px-3 text-sm"
                                :value="String(values[setting.key] ?? '')"
                                @change="values[setting.key] = ($event.target as HTMLSelectElement).value || null"
                            >
                                <option v-for="option in selectOptions[setting.key]" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>

                            <div v-else-if="setting.key === 'localization.available_languages'" :id="setting.key" class="flex flex-wrap gap-4 md:pt-2">
                                <label v-for="language in languageOptions" :key="language.value" class="flex cursor-pointer items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 cursor-pointer"
                                        :checked="languageSelected(setting.key, language.value)"
                                        @change="toggleLanguage(setting.key, language.value, ($event.target as HTMLInputElement).checked)"
                                    />
                                    {{ language.label }}
                                </label>
                            </div>

                            <Input
                                v-else-if="amountKeys.has(setting.key)"
                                :id="setting.key"
                                type="number"
                                min="0"
                                step="0.01"
                                class="max-w-xs"
                                :model-value="String(values[setting.key] ?? '')"
                                @update:model-value="values[setting.key] = String($event)"
                            />

                            <Input
                                v-else-if="inputKind(setting.type) === 'number'"
                                :id="setting.key"
                                type="number"
                                class="max-w-xs"
                                :min="numberRules[setting.key]?.min ?? 0"
                                :max="numberRules[setting.key]?.max"
                                :step="numberRules[setting.key]?.step ?? 1"
                                :model-value="String(values[setting.key] ?? '')"
                                @update:model-value="values[setting.key] = $event === '' ? null : Number($event)"
                            />

                            <Input
                                v-else-if="secretKeys.has(setting.key)"
                                :id="setting.key"
                                type="password"
                                autocomplete="new-password"
                                :model-value="String(values[setting.key] ?? '')"
                                @update:model-value="values[setting.key] = $event"
                            />

                            <textarea
                                v-else-if="longTextKeys.has(setting.key)"
                                :id="setting.key"
                                rows="3"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                :value="String(values[setting.key] ?? '')"
                                @input="values[setting.key] = ($event.target as HTMLTextAreaElement).value"
                            ></textarea>

                            <Input
                                v-else
                                :id="setting.key"
                                type="text"
                                class="block w-full"
                                :placeholder="settingPlaceholders[setting.key]"
                                :model-value="String(values[setting.key] ?? '')"
                                @update:model-value="values[setting.key] = $event"
                            />

                            <p
                                v-if="setting.masked"
                                class="text-muted-foreground text-xs"
                            >
                                The saved value is hidden. Leave it as is to keep it, or type a new one to replace it.
                            </p>

                            <InputError :message="errors[`settings.${setting.key}`]" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 border-t pt-4">
                    <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : 'Save changes' }}</Button>
                    <p class="text-xs text-muted-foreground">Only the {{ groupLabel }} settings on this page are saved.</p>
                </div>
            </form>
        </div>
        </div>
    </div>
</template>
