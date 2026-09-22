<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Navigation } from 'swiper/modules';
import 'swiper/css';
import type { HomeProductItem } from '@/types/marketplace';
import { toProductCardData } from '@/types/marketplace';

const props = defineProps<{
    products: HomeProductItem[];
    loading?: boolean;
}>();

const cards = computed(() => props.products.map(toProductCardData));

const modules = [Navigation];

const navigation = {
    prevEl: '.best-seller-prev',
    nextEl: '.best-seller-next',
};
</script>

<template>
    <section
        aria-label="Best sellers preview"
        class="mt-5 rounded-sm bg-white p-4 shadow-[var(--shadow-card)]"
    >
        <div
            class="flex min-h-[56px] items-center justify-between border-b border-[var(--border-soft)] pb-3"
        >
            <h2 class="text-base font-semibold text-[var(--text-primary)]">
                Best Sellers
            </h2>
            <Link
                href="/products"
                class="text-xs text-[var(--text-secondary)] hover:underline"
            >
                View all &gt;
            </Link>
        </div>
        <div v-if="loading" class="mt-3 flex gap-2 overflow-hidden">
            <div
                v-for="n in 6"
                :key="n"
                class="h-[120px] w-[220px] shrink-0 animate-pulse rounded-sm bg-[var(--bg-muted)]"
            />
        </div>
        <p
            v-else-if="cards.length === 0"
            class="mt-3 rounded-sm bg-[var(--bg-muted)] p-6 text-center text-xs text-[var(--text-muted)]"
        >
            No sales yet. Best sellers will appear once orders come in.
        </p>
        <div v-else class="relative mt-3">
            <button
                type="button"
                aria-label="Previous products"
                class="best-seller-prev absolute top-1/2 left-0 z-10 flex h-9 w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-[var(--border-soft)] bg-white text-xl leading-none text-[var(--text-secondary)] shadow-md transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]"
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
                class="best-seller-swiper overflow-hidden"
            >
                <SwiperSlide
                    v-for="(card, index) in cards"
                    :key="card.id"
                    class="!w-[220px]"
                >
                    <Link
                        :href="`/products/${card.slug}`"
                        class="flex w-full items-center gap-2 rounded-sm border border-[var(--border-soft)] p-2"
                    >
                        <span
                            class="text-2xl font-bold"
                            style="color: var(--brand-primary)"
                        >
                            {{ index + 1 }}
                        </span>
                        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-sm bg-white">
                            <img
                                v-if="card.image"
                                :src="card.image"
                                :alt="card.name"
                                loading="lazy"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-[12px] text-[var(--text-primary)]">
                                {{ card.name }}
                            </p>
                            <p
                                v-if="card.soldCount !== undefined"
                                class="mt-1 text-[11px] text-[var(--text-muted)]"
                            >
                                {{ card.soldCount }} sold
                            </p>
                        </div>
                    </Link>
                </SwiperSlide>
            </Swiper>
            <button
                type="button"
                aria-label="Next products"
                class="best-seller-next absolute top-1/2 right-0 z-10 flex h-9 w-9 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-[var(--border-soft)] bg-white text-xl leading-none text-[var(--text-secondary)] shadow-md transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]"
            >
                ›
            </button>
        </div>
    </section>
</template>

<style scoped>
.best-seller-swiper :deep(.swiper-wrapper) {
    align-items: stretch;
}

.best-seller-prev.swiper-button-disabled,
.best-seller-next.swiper-button-disabled {
    opacity: 0;
    pointer-events: none;
}
</style>
