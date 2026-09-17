<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import type { HomeProductItem, ProductCardData } from '@/types/marketplace';
import { toProductCardData } from '@/types/marketplace';

const props = defineProps<{
    products: HomeProductItem[];
    loading?: boolean;
    title?: string;
}>();

const { formatAmount } = useSettingsStore();

const cards = computed(() => props.products.map(toProductCardData));

function dealPrice(card: ProductCardData): string {
    return formatAmount(card.price);
}

function progressPercent(card: ProductCardData): number {
    if (!card.flashSaleQuantity) return 0;
    return Math.min(100, Math.max(0, ((card.flashSaleSold ?? 0) / card.flashSaleQuantity) * 100));
}
</script>

<template>
    <section
        aria-label="Flash sale preview"
        class="mt-5 rounded-sm bg-white p-4 shadow-[var(--shadow-card)]"
    >
        <div
            class="flex min-h-[56px] items-center justify-between gap-2 border-b border-[var(--border-soft)] pb-3"
        >
            <h2
                class="text-base font-semibold"
                style="color: var(--brand-primary)"
            >
                {{ title ?? 'Flash Sale' }}
            </h2>
            <Link
                href="/flash-sale"
                class="text-xs text-[var(--text-secondary)] hover:underline"
            >
                Lihat Semua &gt;
            </Link>
        </div>
        <p class="mt-2 text-[11px] text-[var(--text-muted)]">
            Promo aktif dari seller. Harga dan kuota mengikuti flash sale produk.
        </p>
        <div v-if="loading" class="mt-3 flex gap-2 overflow-hidden">
            <div
                v-for="n in 6"
                :key="n"
                class="h-[190px] w-[180px] shrink-0 animate-pulse rounded-sm bg-[var(--bg-muted)]"
            />
        </div>
        <p
            v-if="cards.filter((card) => card.flashSaleActive).length === 0"
            class="mt-3 rounded-sm bg-[var(--bg-muted)] p-6 text-center text-xs text-[var(--text-muted)]"
        >
            Flash deals will appear here when promotions are configured.
        </p>
        <div v-else class="mt-3 flex gap-2 overflow-x-auto pb-1">
            <Link
                v-for="card in cards.filter((card) => card.flashSaleActive).slice(0, 8)"
                :key="card.id"
                :href="`/products/${card.slug}`"
                class="w-[180px] shrink-0 overflow-hidden rounded-sm border border-[var(--border-soft)] md:w-[190px]"
            >
                <div class="relative aspect-square bg-white">
                    <img
                        v-if="card.image"
                        :src="card.image"
                        :alt="card.name"
                        loading="lazy"
                        class="h-full w-full object-cover"
                    />
                    <span
                        class="absolute top-0 right-0 bg-[var(--accent-red)] px-1.5 py-0.5 text-[10px] font-bold text-white"
                    >
                        FLASH SALE
                    </span>
                </div>
                <div class="p-2">
                    <p
                        class="text-sm font-semibold"
                        style="color: var(--brand-primary)"
                    >
                        {{ dealPrice(card) }}
                    </p>
                    <div
                        class="mt-1 h-3 overflow-hidden rounded-full bg-[var(--brand-primary-soft)]"
                    >
                        <div
                            class="h-full rounded-full"
                            :style="{ width: `${progressPercent(card)}%`, backgroundColor: 'var(--brand-primary)' }"
                        />
                    </div>
                    <p class="mt-1 text-[11px] text-[var(--text-muted)]">
                        {{ card.flashSaleSold ?? 0 }} Sold Out of {{ card.flashSaleQuantity ?? 0 }} Quotas
                    </p>
                </div>
            </Link>
        </div>
    </section>
</template>
