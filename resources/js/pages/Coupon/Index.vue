<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import { useSettingsStore } from '@/stores/settings';

type Coupon = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    discount_type: string;
    discount_value: string | number;
    maximum_discount: string | number | null;
    minimum_spend: string | number;
    ends_at: string;
    allow_flash_sale: boolean;
};

defineProps<{ coupons: Coupon[] }>();
const { formatAmount } = useSettingsStore();

function discountLabel(coupon: Coupon): string {
    if (coupon.discount_type === 'percentage') return `${coupon.discount_value}% OFF`;
    if (coupon.discount_type === 'free_shipping') return 'GRATIS ONGKIR';

    return `${formatAmount(Number(coupon.discount_value))} OFF`;
}
</script>

<template>
    <Head title="Coupon" />
    <MarketplaceLayout>
        <main class="mx-auto w-full max-w-[1200px] px-4 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-[var(--brand-primary)]">Coupon</h1>
                <p class="mt-1 text-sm text-muted-foreground">Use a coupon to get a better price.</p>
            </div>
            <div v-if="coupons.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <article v-for="coupon in coupons" :key="coupon.id" class="flex flex-col justify-between rounded-xl border border-[var(--border-soft)] bg-white p-5 shadow-sm">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-semibold">{{ coupon.name }}</h2>
                                <p class="mt-1 font-mono text-sm font-bold text-[var(--brand-primary)]">{{ coupon.code }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">{{ discountLabel(coupon) }}</span>
                        </div>
                        <p v-if="coupon.description" class="mt-3 text-sm text-muted-foreground">{{ coupon.description }}</p>
                        <p class="mt-3 text-sm">Minimum spend: <strong>{{ formatAmount(Number(coupon.minimum_spend)) }}</strong></p>
                        <p v-if="coupon.maximum_discount" class="text-sm">Maximum discount: {{ formatAmount(Number(coupon.maximum_discount)) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Berlaku sampai {{ coupon.ends_at }}</p>
                    </div>
                    <Link href="/checkout/cart" class="mt-4 inline-flex w-full justify-center rounded-md bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white">Shop now</Link>
                </article>
            </div>
            <p v-else class="rounded-xl border p-8 text-center text-sm text-muted-foreground">No active coupons yet.</p>
        </main>
    </MarketplaceLayout>
</template>
