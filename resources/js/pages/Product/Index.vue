<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import ProductCard from '@/components/product/ProductCard.vue';
import {
    toProductCardData,
    type HomeProductItem,
} from '@/types/marketplace';
import { useSettingsStore } from '@/stores/settings';

type ProductListImage = {
    id: number;
    url: string;
    sort_order: number;
    is_primary: boolean;
};

type ProductListItem = {
    id: number;
    name: string;
    slug: string;
    price: string | number;
    stock: number;
    status: string;
    category: { id: number; name: string; slug: string } | null;
    images: ProductListImage[];
    primary_image: string | null;
    normal_price?: string | number;
    effective_price?: string | number;
    flash_sale_active?: boolean;
    seller?: { store_name: string; slug: string } | null;
    average_rating?: number | null;
    ratings_count?: number;
};

type ProductCategory = {
    id: number;
    name: string;
    slug: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type PaginatedProducts = {
    data: ProductListItem[];
    links: PaginationLink[] | Record<string, string | null>;
    meta?: { total: number; links?: PaginationLink[] };
    current_page?: number;
    last_page?: number;
};

const props = defineProps<{
    products: PaginatedProducts;
    categories: ProductCategory[] | { data: ProductCategory[] };
    filters: { category: string; search: string; sort: string };
}>();

const { formatAmount } = useSettingsStore();
const search = ref(props.filters.search);
const sort = ref(props.filters.sort);
const selectedCategory = ref(props.filters.category);

const sortOptions = [
    { value: 'latest', label: 'Newest' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
    { value: 'name', label: 'Name' },
];

function unwrapCategories(value: ProductCategory[] | { data: ProductCategory[] }): ProductCategory[] {
    return Array.isArray(value) ? value : (value?.data ?? []);
}

const categoriesList = computed<ProductCategory[]>(() => unwrapCategories(props.categories));

const productCards = computed(() =>
    props.products.data.map((item) =>
        toProductCardData(item as unknown as HomeProductItem),
    ),
);

const paginationLinks = computed<PaginationLink[]>(() => {
    const metaLinks = props.products.meta?.links;
    if (Array.isArray(metaLinks) && metaLinks.length > 0) {
        return metaLinks;
    }
    if (Array.isArray(props.products.links)) {
        return props.products.links;
    }
    return [];
});

const totalProducts = computed<number>(() => props.products.meta?.total ?? 0);

const isEmpty = computed(() => props.products.data.length === 0);

const hasAnyFilter = computed(
    () =>
        selectedCategory.value !== '' ||
        search.value.trim() !== '',
);

function applyFilters(): void {
    router.get(
        '/products',
        {
            category: selectedCategory.value || undefined,
            search: search.value || undefined,
            sort: sort.value,
        },
        { preserveState: true, replace: true },
    );
}

function selectCategory(slug: string): void {
    selectedCategory.value = slug;
    search.value = '';
    applyFilters();
}

function resetFilters(): void {
    search.value = '';
    sort.value = 'latest';
    selectedCategory.value = '';
    router.get('/products', {}, { replace: true });
}
</script>

<template>
    <Head title="All Products" />

    <MarketplaceLayout>
        <div class="mx-auto w-full max-w-[1200px] px-4 py-6 pb-24 md:pb-6">
            <nav class="mb-4 text-xs text-[var(--text-muted)]">
                <Link href="/" class="hover:underline">Home</Link>
                <span class="mx-1">/</span>
                <span class="text-[var(--text-primary)]">All Products</span>
            </nav>

            <div class="mt-5 flex flex-col gap-6 md:flex-row">
                <aside class="w-full shrink-0 md:w-[220px]">
                    <div class="rounded-sm bg-white p-4 shadow-[var(--shadow-card)]">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-[var(--text-primary)]">
                                Categories
                            </h2>
                            <button
                                v-if="selectedCategory !== ''"
                                type="button"
                                class="text-xs text-[var(--brand-primary)] hover:underline"
                                @click="
                                    selectedCategory = '';
                                    applyFilters();
                                "
                            >
                                Reset
                            </button>
                        </div>
                        <ul class="space-y-1 text-sm">
                            <li>
                                <button
                                    type="button"
                                    :class="[
                                        'w-full rounded-sm px-2 py-1.5 text-left transition',
                                        selectedCategory === ''
                                            ? 'bg-[var(--brand-primary-soft)] font-semibold text-[var(--brand-primary)]'
                                            : 'text-[var(--text-secondary)] hover:bg-[var(--bg-muted)]',
                                    ]"
                                    @click="
                                        selectCategory('')
                                    "
                                >
                                    All products
                                </button>
                            </li>
                            <li
                                v-for="category in categoriesList"
                                :key="category.id"
                            >
                                <button
                                    type="button"
                                    :class="[
                                        'w-full rounded-sm px-2 py-1.5 text-left transition',
                                        selectedCategory === category.slug
                                            ? 'bg-[var(--brand-primary-soft)] font-semibold text-[var(--brand-primary)]'
                                            : 'text-[var(--text-secondary)] hover:bg-[var(--bg-muted)]',
                                    ]"
                                    @click="
                                        selectCategory(category.slug)
                                    "
                                >
                                    {{ category.name }}
                                </button>
                            </li>
                        </ul>
                        <p
                            v-if="categoriesList.length === 0"
                            class="py-2 text-xs text-[var(--text-muted)]"
                        >
                            No categories yet.
                        </p>
                    </div>
                </aside>

                <section class="min-w-0 flex-1">
                    <div
                        class="mb-4 flex flex-col gap-2 rounded-sm bg-white p-3 shadow-[var(--shadow-card)] sm:flex-row sm:items-center"
                    >
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search products..."
                            class="h-10 flex-1 rounded-sm border border-[var(--border-default)] px-3 text-sm text-[var(--text-primary)] placeholder:text-[var(--text-faint)] focus:border-[var(--brand-primary)] focus:outline-none"
                            @keyup.enter="applyFilters"
                        />
                        <select
                            v-model="sort"
                            class="h-10 rounded-sm border border-[var(--border-default)] bg-white px-3 text-sm text-[var(--text-primary)] focus:border-[var(--brand-primary)] focus:outline-none"
                            @change="applyFilters"
                        >
                            <option
                                v-for="option in sortOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                Sort: {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="mb-3 flex items-center justify-between text-xs text-[var(--text-muted)]"
                    >
                        <span>
                            <span v-if="totalProducts > 0">{{ totalProducts }}</span>
                            <span v-else>{{ props.products.data.length }}</span>
                            <span class="ml-1">products found</span>
                            <span
                                v-if="hasAnyFilter"
                                class="ml-1 text-[var(--text-secondary)]"
                            >
                                (filtered)
                            </span>
                        </span>
                        <button
                            v-if="hasAnyFilter"
                            type="button"
                            class="font-medium text-[var(--brand-primary)] hover:underline"
                            @click="resetFilters"
                        >
                            Clear filters
                        </button>
                    </div>

                    <div
                        v-if="isEmpty"
                        class="rounded-sm border border-[var(--border-soft)] bg-white p-10 text-center shadow-[var(--shadow-card)]"
                    >
                        <div
                            class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-[var(--bg-muted)] text-2xl text-[var(--text-muted)]"
                            aria-hidden="true"
                        >
                            📦
                        </div>
                        <p class="text-sm font-medium text-[var(--text-primary)]">
                            No products found
                        </p>
                        <p class="mt-1 text-xs text-[var(--text-muted)]">
                            Try a different search term or clear the filters.
                        </p>
                        <button
                            v-if="hasAnyFilter"
                            type="button"
                            class="mt-4 inline-flex h-10 items-center justify-center rounded-sm border border-[var(--brand-primary)] bg-white px-4 text-sm font-medium text-[var(--brand-primary)] hover:bg-[var(--brand-primary-soft)]"
                            @click="resetFilters"
                        >
                            Reset all filters
                        </button>
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5"
                    >
                        <ProductCard
                            v-for="card in productCards"
                            :key="card.id"
                            :product="card"
                        />
                    </div>

                    <nav
                        v-if="paginationLinks.length > 3"
                        class="mt-6 flex flex-wrap items-center gap-1 rounded-sm bg-white p-2 shadow-[var(--shadow-card)]"
                    >
                        <Link
                            v-for="(link, index) in paginationLinks"
                            :key="index"
                            :href="link.url ?? '#'"
                            :class="[
                                'inline-flex min-w-[2.25rem] items-center justify-center rounded-sm border px-3 py-1.5 text-sm transition',
                                !link.url
                                    ? 'cursor-not-allowed border-[var(--border-soft)] text-[var(--text-faint)]'
                                    : link.active
                                      ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] font-semibold text-white'
                                      : 'border-[var(--border-default)] text-[var(--text-secondary)] hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]',
                            ]"
                            :disabled="!link.url"
                            v-html="link.label"
                        />
                    </nav>
                </section>
            </div>
        </div>
    </MarketplaceLayout>
</template>
