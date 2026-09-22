<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Heart,
    LifeBuoy,
    Sparkles,
    Store,
    Tag,
    Ticket,
    Truck,
    Zap,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { sellerCenter } from '@/routes';
import { index as productsIndex } from '@/routes/products';
import { show as profileShow } from '@/routes/profile';
import { useSettingsStore } from '@/stores/settings';
import type { HomeCategoryItem } from '@/types/marketplace';

type ServiceTile = {
    key: string;
    label: string;
    href: string;
    icon: Component;
};

const props = withDefaults(
    defineProps<{
        categories?: HomeCategoryItem[];
    }>(),
    { categories: () => [] },
);

const MAX_TILES = 10;

const { getSettingValue } = useSettingsStore();

const services = computed<ServiceTile[]>(() => {
    const leading: ServiceTile[] = [
        { key: 'flash-sale', label: 'Flash Sale', href: '/flash-sale', icon: Zap },
        { key: 'vouchers', label: 'Vouchers', href: '/coupons', icon: Ticket },
        {
            key: 'new-arrivals',
            label: 'New Arrivals',
            href: productsIndex({ query: { sort: 'latest' } }).url,
            icon: Sparkles,
        },
    ];

    const trailing: ServiceTile[] = [
        {
            key: 'favorites',
            label: 'Favorites',
            href: profileShow({ query: { tab: 'favorite_products' } }).url,
            icon: Heart,
        },
        { key: 'track-order', label: 'Track Order', href: '/tracking', icon: Truck },
        {
            key: 'sell',
            label: `Sell on ${getSettingValue<string>('branding.site_name', 'Barokah')}`,
            href: sellerCenter().url,
            icon: Store,
        },
        { key: 'help', label: 'Help', href: '/help', icon: LifeBuoy },
    ];

    // Categories fill the remaining slots, in the order admins set.
    const categoryTiles: ServiceTile[] = props.categories
        .slice(0, Math.max(0, MAX_TILES - leading.length - trailing.length))
        .map((category) => ({
            key: `category-${category.slug}`,
            label: category.name,
            href: productsIndex({ query: { category: category.slug } }).url,
            icon: Tag,
        }));

    return [...leading, ...categoryTiles, ...trailing];
});

</script>

<template>
    <section
        aria-label="Quick services"
        class="mt-5 rounded-sm bg-white p-4 shadow-[var(--shadow-card)]"
    >
        <div
            class="flex min-h-[56px] items-center border-b border-[var(--border-soft)] pb-3"
        >
            <h2 class="text-base font-semibold text-[var(--text-primary)]">
                Quick Services
            </h2>
        </div>
        <div
            class="mt-3 grid grid-cols-5 gap-2 md:grid-cols-8 lg:grid-cols-10"
        >
            <Link
                v-for="service in services"
                :key="service.key"
                :href="service.href"
                class="group flex flex-col items-center gap-1.5 rounded-sm p-2 transition hover:-translate-y-0.5"
            >
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-[var(--brand-primary-soft)] text-[var(--brand-primary)] transition group-hover:bg-[var(--brand-primary)] group-hover:text-white md:h-[48px] md:w-[48px]"
                    aria-hidden="true"
                >
                    <component :is="service.icon" class="h-5 w-5" />
                </span>
                <span
                    class="line-clamp-2 text-center text-[11px] text-[var(--text-secondary)] md:text-[12px]"
                >
                    {{ service.label }}
                </span>
            </Link>
        </div>
    </section>
</template>
