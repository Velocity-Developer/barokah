<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { index as productsIndex } from '@/routes/products';
import type { HomeCategoryItem } from '@/types/marketplace';

const props = defineProps<{
    categories: HomeCategoryItem[];
    loading?: boolean;
}>();

const tiles = computed(() =>
    props.categories.map((category) => ({
        id: category.id,
        name: category.name,
        href: productsIndex({ query: { category: category.slug } }).url,
        image: category.image_url ?? null,
        count: category.products_count,
    })),
);

// A missing picture falls back to the icon instead of a broken image.
const brokenImages = ref<Set<number>>(new Set());

function countLabel(count: number | undefined): string | null {
    if (count === undefined) {
        return null;
    }

    return count === 1 ? '1 product' : `${count} products`;
}
</script>

<template>
    <section
        aria-label="Categories"
        class="mt-5 rounded-sm bg-white p-4 shadow-[var(--shadow-card)]"
    >
        <div
            class="flex min-h-[56px] items-center justify-between gap-3 border-b border-[var(--border-soft)] pb-3"
        >
            <div>
                <h2 class="mp-section-title">
                    Categories
                </h2>
                <p class="text-xs text-[var(--text-muted)]">
                    Browse products by category
                </p>
            </div>
            <Link
                :href="productsIndex()"
                class="flex shrink-0 items-center gap-0.5 text-xs font-medium text-[var(--brand-primary)] hover:underline"
            >
                See all products
                <ChevronRight class="h-3.5 w-3.5" aria-hidden="true" />
            </Link>
        </div>

        <div
            v-if="loading"
            class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
        >
            <div
                v-for="n in 6"
                :key="n"
                class="aspect-[4/3] animate-pulse rounded-sm bg-[var(--bg-muted)]"
            />
        </div>
        <div
            v-else-if="tiles.length === 0"
            class="mt-4 rounded-sm bg-[var(--bg-muted)] p-6 text-center text-xs text-[var(--text-muted)]"
        >
            Categories will appear here once created.
        </div>
        <!-- auto-fit: few categories stretch across the row, more wrap into 170px+ tiles. -->
        <div
            v-else
            class="mt-4 grid grid-cols-3 gap-2.5 sm:grid-cols-[repeat(auto-fit,minmax(170px,1fr))] sm:gap-4"
        >
            <Link
                v-for="tile in tiles"
                :key="tile.id"
                :href="tile.href"
                class="group relative block aspect-[3/4] overflow-hidden rounded-lg bg-gradient-to-br from-[var(--brand-primary-soft)] to-white ring-1 ring-[var(--border-default)] transition hover:-translate-y-1 hover:shadow-[var(--shadow-hover)] sm:aspect-[4/3]"
            >
                <img
                    v-if="tile.image && !brokenImages.has(tile.id)"
                    :src="tile.image"
                    alt=""
                    loading="lazy"
                    class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    @error="brokenImages = new Set([...brokenImages, tile.id])"
                />
                <span
                    v-else
                    class="absolute inset-0 flex items-center justify-center text-[var(--brand-primary)]"
                    aria-hidden="true"
                >
                    <Sparkles class="h-10 w-10 opacity-50 sm:h-14 sm:w-14" />
                </span>
                <span
                    class="absolute inset-0 bg-gradient-to-t from-[color-mix(in_srgb,var(--accent-navy)_85%,transparent)] via-[color-mix(in_srgb,var(--brand-primary)_20%,transparent)] to-transparent"
                    aria-hidden="true"
                />
                <span class="absolute inset-x-0 bottom-0 p-2.5 text-white sm:p-5">
                    <span
                        class="block truncate font-[family-name:var(--font-display)] text-base font-bold sm:text-2xl"
                    >
                        {{ tile.name }}
                    </span>
                    <span
                        v-if="countLabel(tile.count)"
                        class="hidden text-xs text-white/85 sm:block"
                    >
                        {{ countLabel(tile.count) }}
                    </span>
                    <span
                        class="mt-2 hidden items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-bold text-[var(--brand-primary)] transition group-hover:bg-[var(--brand-primary)] group-hover:text-white sm:inline-flex"
                    >
                        Shop now
                        <ChevronRight class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                </span>
            </Link>
        </div>
    </section>
</template>
