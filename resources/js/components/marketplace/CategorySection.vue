<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Tag } from '@lucide/vue';
import { computed } from 'vue';
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
                <h2 class="text-base font-semibold text-[var(--text-primary)]">
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
            class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(170px,1fr))]"
        >
            <Link
                v-for="tile in tiles"
                :key="tile.id"
                :href="tile.href"
                class="group relative block aspect-[4/3] overflow-hidden sm:aspect-[16/10] rounded-sm border border-[var(--border-soft)] bg-[var(--brand-primary-soft)]"
            >
                <img
                    v-if="tile.image"
                    :src="tile.image"
                    alt=""
                    loading="lazy"
                    class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105"
                />
                <span
                    v-else
                    class="absolute inset-0 flex items-center justify-center text-[var(--brand-primary)]"
                    aria-hidden="true"
                >
                    <Tag class="h-10 w-10 opacity-60" />
                </span>
                <span
                    class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"
                    aria-hidden="true"
                />
                <span class="absolute inset-x-0 bottom-0 p-3 text-white">
                    <span class="block truncate text-sm font-semibold">
                        {{ tile.name }}
                    </span>
                    <span
                        v-if="countLabel(tile.count)"
                        class="block text-[11px] text-white/85"
                    >
                        {{ countLabel(tile.count) }}
                    </span>
                </span>
            </Link>
        </div>
    </section>
</template>
