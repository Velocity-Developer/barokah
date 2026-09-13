<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import { useSettingsStore } from '@/stores/settings';

const slides = computed(() =>
    Array.from({ length: 10 }, (_, index) => index).filter((index) =>
        bannerUrl(index),
    ).length > 0
        ? Array.from({ length: 10 }, (_, index) => index).filter((index) => bannerUrl(index))
        : [0],
);
const { getSettingValue, loadSettings } = useSettingsStore();

const sliderSpeed = computed(() => Math.max(1000, Number(getSettingValue('homepage.banner_speed', 5000))));
const rightTopBanner = computed(() => getSettingValue<string>('homepage.right_top_banner_url', ''));
const rightBottomBanner = computed(() => getSettingValue<string>('homepage.right_bottom_banner_url', ''));

void loadSettings();

function bannerUrl(index: number): string {
    return getSettingValue<string>(`homepage.banner_${index + 1}_url`, '');
}
const activeSlide = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

const sidePromos = computed(() => [
    { title: 'Rigth Top Banner', url: rightTopBanner.value },
    { title: 'Right Bottom Banner', url: rightBottomBanner.value },
]);

const reduceMotion = computed(
    () =>
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches,
);

function goTo(index: number): void {
    activeSlide.value = (index + slides.value.length) % slides.value.length;
}

function startAutoplay(): void {
    if (reduceMotion.value || timer !== null) {
        return;
    }
    timer = setInterval(() => {
        goTo(activeSlide.value + 1);
    }, sliderSpeed.value);
}

watch(sliderSpeed, () => {
    stopAutoplay();
    startAutoplay();
});

function stopAutoplay(): void {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
}

onMounted(() => {
    startAutoplay();
});

onUnmounted(() => {
    stopAutoplay();
});
</script>

<template>
    <section aria-label="Promotions" class="mt-4">
        <div class="grid gap-2 md:grid-cols-3">
            <div
                class="relative overflow-hidden rounded-sm bg-[var(--brand-primary-soft)] md:col-span-2"
                style="min-height: 240px"
            >
                <div
                    v-for="(slide, index) in slides"
                    :key="slide"
                    :class="[
                        'absolute inset-0 flex flex-col items-start justify-center gap-2 p-6 transition-opacity duration-500 md:p-10',
                        index === activeSlide
                            ? 'opacity-100'
                            : 'pointer-events-none opacity-0',
                    ]"
                    :aria-hidden="index !== activeSlide"
                >
                    <img
                        v-if="bannerUrl(slide)"
                        :src="bannerUrl(slide)"
                        :alt="`Banner ${index + 1}`"
                        class="absolute inset-0 size-full object-cover"
                    />
                    <div v-if="!bannerUrl(slide)" class="relative">
                    <p
                        class="text-lg font-bold md:text-2xl"
                        style="color: var(--brand-primary)"
                    >
                        Barokah Marketplace Promo {{ index + 1 }}
                    </p>
                    <p class="text-sm text-[var(--text-secondary)]">
                        Static banner art. Admin-managed banners are TBC (spec
                        §24 item 27).
                    </p>
                    </div>
                </div>
                <div
                    class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5"
                >
                    <button
                        v-for="(slide, index) in slides"
                        :key="slide"
                        type="button"
                        :aria-label="`Go to banner ${index + 1}`"
                        :class="[
                            'h-2 rounded-full transition-all',
                            index === activeSlide
                                ? 'w-6 bg-[var(--brand-primary)]'
                                : 'w-2 bg-black/20',
                        ]"
                        @click="goTo(index)"
                    />
                </div>
            </div>
            <div class="hidden grid-rows-2 gap-2 md:grid">
                <div
                    v-for="promo in sidePromos"
                    :key="promo.title"
                    class="flex flex-col justify-center rounded-sm bg-[var(--bg-surface)] p-4 shadow-[var(--shadow-card)]"
                >
                    <img v-if="promo.url" :src="promo.url" :alt="promo.title" class="size-full object-cover" />
                    <p v-else class="text-sm font-semibold">{{ promo.title }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
