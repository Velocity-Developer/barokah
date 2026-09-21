<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Navigation } from 'swiper/modules';
import 'swiper/css';
import { useSettingsStore } from '@/stores/settings';
import type { HomeProductItem, ProductCardData } from '@/types/marketplace';
import { toProductCardData } from '@/types/marketplace';

const props = defineProps<{
    products: HomeProductItem[];
    loading?: boolean;
    title?: string;
}>();

const { formatAmount } = useSettingsStore();

const cards = computed(() =>
    props.products
        .map(toProductCardData)
        .filter((card) => card.flashSaleActive)
        .slice(0, 8),
);

const modules = [Navigation];

const navigation = {
    prevEl: '.flash-sale-prev',
    nextEl: '.flash-sale-next',
};

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
            <Link href="/flash-sale" class="text-xs text-[var(--text-secondary)] hover:underline">
                View all &gt;
            </Link>
        </div>
        <p class="mt-2 text-[11px] text-[var(--text-muted)]">
            Active promotions from seller products.
        </p>
        <div v-if="loading" class="mt-3 flex gap-2 overflow-hidden">
            <div
                v-for="n in 6"
                :key="n"
                class="h-[190px] w-[180px] shrink-0 animate-pulse rounded-sm bg-[var(--bg-muted)] md:w-[190px]"
            />
        </div>
        <p
            v-else-if="cards.length === 0"
            class="mt-3 rounded-sm bg-[var(--bg-muted)] p-6 text-center text-xs text-[var(--text-muted)]"
        >
            Flash deals will appear here when promotions are configured.
        </p>
        <div v-else class="relative mt-3">
            <button
                type="button"
                aria-label="Produk sebelumnya"
                class="flash-sale-prev absolute top-1/2 left-0 z-10 flex h-9 w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-[var(--border-soft)] bg-white text-xl leading-none text-[var(--text-secondary)] shadow-md transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]"
            >
                ‹
            </button>
            <Swiper
                :modules="modules"
                :navigation="navigation"
                :slides-per-view="'auto'"
                :space-between="8"
                :watch-slides-progress="true"
                :grab-cursor="true"
                :slides-per-group="1"
                :watch-overflow="true"
                :resistance="true"
                :resistance-ratio="0.85"
                class="flash-sale-swiper overflow-hidden"
            >
                <SwiperSlide
                    v-for="card in cards"
                    :key="card.id"
                    class="!w-[180px] md:!w-[190px]"
                >
                    <Link
                        :href="`/products/${card.slug}`"
                        class="block h-full overflow-hidden rounded-sm border border-[var(--border-soft)]"
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
                                class="absolute top-2 right-2 rounded-sm bg-[var(--accent-red)] px-2 py-1 text-[10px] font-bold text-white shadow-sm"
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
                </SwiperSlide>
            </Swiper>
            <button
                type="button"
                aria-label="Produk berikutnya"
                class="flash-sale-next absolute top-1/2 right-0 z-10 flex h-9 w-9 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-[var(--border-soft)] bg-white text-xl leading-none text-[var(--text-secondary)] shadow-md transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]"
            >
                ›
            </button>
        </div>
    </section>
</template>

<style scoped>
.flash-sale-swiper :deep(.swiper-wrapper) {
    align-items: stretch;
}

.flash-sale-prev.swiper-button-disabled,
.flash-sale-next.swiper-button-disabled {
    opacity: 0;
    pointer-events: none;
}
</style>
