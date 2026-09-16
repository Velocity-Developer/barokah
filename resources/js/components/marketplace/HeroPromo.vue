<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import { useSettingsStore } from '@/stores/settings';

const slides = computed(() =>
    Array.from({ length: 10 }, (_, index) => index).filter((index) =>
        bannerUrl(index),
    ),
);
const { getSettingValue } = useSettingsStore();

const sliderSpeed = computed(() => Math.max(1000, Number(getSettingValue('homepage.banner_speed', 5000))));
const rightTopBanner = computed(() => getSettingValue<string>('homepage.right_top_banner_url', ''));
const rightBottomBanner = computed(() => getSettingValue<string>('homepage.right_bottom_banner_url', ''));
const rightTopBannerLink = computed(() => getSettingValue<string>('homepage.right_top_banner_link', ''));
const rightBottomBannerLink = computed(() => getSettingValue<string>('homepage.right_bottom_banner_link', ''));

function bannerUrl(index: number): string {
    return getSettingValue<string>(`homepage.banner_${index + 1}_url`, '');
}

function bannerLink(index: number): string {
    return getSettingValue<string>(`homepage.banner_${index + 1}_link`, '');
}
const activeSlide = ref(0);
const loadedBanners = ref<Set<string>>(new Set());
let timer: ReturnType<typeof setInterval> | null = null;

function markBannerLoaded(url: string): void {
    loadedBanners.value = new Set([...loadedBanners.value, url]);
}

const sidePromos = computed(() => [
    { title: 'Rigth Top Banner', url: rightTopBanner.value, link: rightTopBannerLink.value },
    { title: 'Right Bottom Banner', url: rightBottomBanner.value, link: rightBottomBannerLink.value },
]);

const reduceMotion = computed(
    () =>
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches,
);

function goTo(index: number): void {
    if (slides.value.length === 0) {
        return;
    }

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
                style="min-height: 350px"
            >
                <a
                    v-for="(slide, index) in slides"
                    :key="slide"
                    :href="bannerLink(slide) || undefined"
                    :target="bannerLink(slide) ? '_blank' : undefined"
                    :rel="bannerLink(slide) ? 'noopener noreferrer' : undefined"
                    :class="[
                        'absolute inset-0 flex flex-col items-start justify-center gap-2 p-6 transition-opacity duration-500 md:p-10',
                        index === activeSlide
                            ? 'opacity-100'
                            : 'pointer-events-none opacity-0',
                    ]"
                    :aria-hidden="index !== activeSlide"
                >
                    <div
                        v-if="bannerUrl(slide) && !loadedBanners.has(bannerUrl(slide))"
                        class="absolute inset-0 animate-pulse bg-muted"
                        aria-label="Loading banner"
                    />
                    <img
                        v-if="bannerUrl(slide)"
                        :src="bannerUrl(slide)"
                        :alt="`Banner ${index + 1}`"
                        class="absolute inset-0 size-full object-cover"
                        :class="loadedBanners.has(bannerUrl(slide)) ? 'opacity-100' : 'opacity-0'"
                        @load="markBannerLoaded(bannerUrl(slide))"
                    />
                    <div
                        v-if="!bannerUrl(slide)"
                        class="absolute inset-0 animate-pulse bg-muted"
                        aria-label="Loading banner"
                    />
                </a>
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
            <div class="hidden h-[350px] grid-rows-2 gap-2 md:grid">
                <a
                    v-for="promo in sidePromos"
                    :key="promo.title"
                    :href="promo.link || undefined"
                    :target="promo.link ? '_blank' : undefined"
                    :rel="promo.link ? 'noopener noreferrer' : undefined"
                    class="relative flex min-h-0 flex-col justify-center overflow-hidden rounded-sm bg-[var(--bg-surface)] shadow-[var(--shadow-card)]"
                >
                    <div
                        class="absolute inset-0 animate-pulse bg-muted"
                        :class="promo.url && loadedBanners.has(promo.url) ? 'hidden' : 'block'"
                        aria-label="Loading banner"
                    />
                    <img
                        v-if="promo.url"
                        :src="promo.url"
                        :alt="promo.title"
                        class="relative h-full w-full object-cover"
                        :class="loadedBanners.has(promo.url) ? 'opacity-100' : 'opacity-0'"
                        @load="markBannerLoaded(promo.url)"
                    />
                </a>
            </div>
        </div>
    </section>
</template>
