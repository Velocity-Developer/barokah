<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Mail, MapPin, MessageCircle, Phone } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { sellerCenter } from '@/routes';
import { index as productsIndex } from '@/routes/products';
import { show as profileShow } from '@/routes/profile';
import { useSettingsStore } from '@/stores/settings';

type FooterLink = { label: string; href: string };
type ContactItem = { icon: Component; label: string; href?: string };

const { getSettingValue } = useSettingsStore();

const siteName = computed(() => getSettingValue<string>('branding.site_name', 'Barokah'));
const logoUrl = computed(() => getSettingValue<string>('branding.logo_url', ''));
const about = computed(
    () =>
        getSettingValue<string>('marketplace.description', '') ||
        getSettingValue<string>('general.site_tagline', '') ||
        'Multi-seller marketplace.',
);

const shopLinks: FooterLink[] = [
    { label: 'All products', href: productsIndex().url },
    { label: 'Flash sale', href: '/flash-sale' },
    { label: 'Coupons', href: '/coupons' },
    { label: 'Keripik', href: productsIndex({ query: { category: 'keripik' } }).url },
    { label: 'Hijab', href: productsIndex({ query: { category: 'hijab' } }).url },
    { label: 'Kerudung', href: productsIndex({ query: { category: 'kerudung' } }).url },
];

const accountLinks: FooterLink[] = [
    { label: 'Track order', href: '/tracking' },
    { label: 'My profile', href: profileShow().url },
    { label: 'Seller center', href: sellerCenter().url },
    { label: 'Cart', href: '/cart' },
];

const contactItems = computed<ContactItem[]>(() => {
    const items: ContactItem[] = [];
    const email = getSettingValue<string>('contact.email', '');
    const phone = getSettingValue<string>('contact.phone', '');
    const whatsapp = getSettingValue<string>('contact.whatsapp', '');
    const address = getSettingValue<string>('contact.address', '');
    const hours = getSettingValue<string>('contact.business_hours', '');
    const whatsappDigits = whatsapp.replace(/\D/g, '');

    if (email) items.push({ icon: Mail, label: email, href: `mailto:${email}` });
    if (phone) items.push({ icon: Phone, label: phone, href: `tel:${phone.replace(/[^\d+]/g, '')}` });
    if (whatsappDigits) items.push({ icon: MessageCircle, label: `WhatsApp ${whatsapp}`, href: `https://wa.me/${whatsappDigits}` });
    if (address) {
        items.push({
            icon: MapPin,
            label: address,
            href: `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`,
        });
    }
    if (hours) items.push({ icon: Clock, label: hours });

    return items;
});

/** mailto:/tel: links are handed to the device, not navigated. */
function isAppLink(href: string): boolean {
    return href.startsWith('mailto:') || href.startsWith('tel:');
}

/** Links to another host (wa.me, maps, velocitydeveloper.com, …) open in a new tab. */
function isExternal(href: string): boolean {
    if (isAppLink(href)) {
        return false;
    }

    try {
        return new URL(href, window.location.origin).origin !== window.location.origin;
    } catch {
        return false;
    }
}
</script>

<template>
    <!-- Bottom padding on mobile keeps the fixed bottom nav from covering the credits. -->
    <footer class="mt-5 border-t border-[var(--border-default)] bg-white pb-16 md:pb-0">
        <div
            class="mx-auto grid w-full grid-cols-2 gap-x-6 gap-y-8 px-4 py-10 lg:grid-cols-[1.4fr_1fr_1fr_1.4fr]"
            style="max-width: var(--container-max)"
        >
            <div class="col-span-2 lg:col-span-1">
                <Link href="/" class="inline-flex items-center gap-2" :aria-label="siteName">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="siteName"
                        class="h-9 max-w-32 object-contain"
                    />
                    <span
                        v-else
                        class="flex h-9 w-9 items-center justify-center rounded-sm text-lg font-bold text-white"
                        style="background-color: var(--brand-primary)"
                        aria-hidden="true"
                    >
                        {{ siteName.charAt(0) }}
                    </span>
                    <span class="text-base font-bold">{{ siteName }}</span>
                </Link>
                <p class="mt-3 max-w-xs text-xs leading-relaxed text-[var(--text-muted)]">
                    {{ about }}
                </p>
            </div>

            <nav
                v-for="group in [
                    { title: 'Shop', links: shopLinks },
                    { title: 'Account', links: accountLinks },
                ]"
                :key="group.title"
                :aria-label="group.title"
            >
                <p class="text-sm font-semibold">{{ group.title }}</p>
                <ul class="mt-3 space-y-2 text-xs text-[var(--text-secondary)]">
                    <li v-for="link in group.links" :key="link.href">
                        <a
                            v-if="isExternal(link.href)"
                            :href="link.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hover:text-[var(--brand-primary)] hover:underline"
                        >
                            {{ link.label }}
                        </a>
                        <Link
                            v-else
                            :href="link.href"
                            class="hover:text-[var(--brand-primary)] hover:underline"
                        >
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="col-span-2 lg:col-span-1">
                <p class="text-sm font-semibold">Contact us</p>
                <ul
                    v-if="contactItems.length"
                    class="mt-3 space-y-2.5 text-xs text-[var(--text-secondary)]"
                >
                    <li
                        v-for="item in contactItems"
                        :key="item.label"
                        class="flex items-start gap-2"
                    >
                        <component
                            :is="item.icon"
                            class="mt-px h-3.5 w-3.5 shrink-0 text-[var(--text-muted)]"
                            aria-hidden="true"
                        />
                        <a
                            v-if="item.href && isExternal(item.href)"
                            :href="item.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="break-words hover:text-[var(--brand-primary)] hover:underline"
                        >
                            {{ item.label }}
                        </a>
                        <a
                            v-else-if="item.href && isAppLink(item.href)"
                            :href="item.href"
                            class="break-words hover:text-[var(--brand-primary)] hover:underline"
                        >
                            {{ item.label }}
                        </a>
                        <Link
                            v-else-if="item.href"
                            :href="item.href"
                            class="break-words hover:text-[var(--brand-primary)] hover:underline"
                        >
                            {{ item.label }}
                        </Link>
                        <span v-else class="break-words">{{ item.label }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-[var(--border-soft)]">
            <div
                class="mx-auto flex w-full flex-col items-center justify-between gap-1 px-4 py-4 text-[11px] text-[var(--text-muted)] sm:flex-row"
                style="max-width: var(--container-max)"
            >
                <p>© {{ new Date().getFullYear() }} {{ siteName }}. All rights reserved.</p>
                <p>
                    Design by
                    <a
                        href="https://velocitydeveloper.com/"
                        target="_blank"
                        rel="noopener"
                        class="font-medium text-[var(--text-secondary)] hover:text-[var(--brand-primary)] hover:underline"
                    >
                        Velocity Developer
                    </a>
                </p>
            </div>
        </div>
    </footer>
</template>
