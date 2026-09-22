<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ImageOff, Search, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { edit, index, show } from '@/routes/admin/products';
import { formatPrice } from '@/services/priceFormatter';

type AdminProduct = {
    id: number;
    name: string;
    slug: string;
    status: string;
    price: string | number;
    effective_price?: string | number;
    flash_sale_active?: boolean;
    stock: number;
    sold_count?: number;
    primary_image?: string | null;
    seller?: { id: number; store_name: string } | null;
    category?: { id: number; name: string } | null;
};

type PageMeta = { current_page: number; last_page: number; total: number; from: number | null; to: number | null };

const props = defineProps<{
    sellers: { id: number; store_name: string }[];
    categories: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});

const LOW_STOCK = 5;

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'draft', label: 'Draft' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'archived', label: 'Archived' },
];

const STOCK_OPTIONS = [
    { value: 'in_stock', label: 'In stock' },
    { value: 'low', label: `Low stock (≤ ${LOW_STOCK})` },
    { value: 'out', label: 'Out of stock' },
];

const SORTS = [
    { value: 'latest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'name_asc', label: 'Name A–Z' },
    { value: 'name_desc', label: 'Name Z–A' },
    { value: 'price_asc', label: 'Lowest price' },
    { value: 'price_desc', label: 'Highest price' },
    { value: 'stock_asc', label: 'Lowest stock' },
    { value: 'stock_desc', label: 'Highest stock' },
    { value: 'sold_desc', label: 'Best selling' },
];

const FILTER_KEYS = ['search', 'status', 'seller_id', 'category_id', 'stock', 'sort', 'per_page'] as const;
type FilterKey = (typeof FILTER_KEYS)[number];

const DEFAULTS: Record<FilterKey, string> = {
    search: '',
    status: '',
    seller_id: '',
    category_id: '',
    stock: '',
    sort: 'latest',
    per_page: '15',
};

const filters = reactive<Record<FilterKey, string>>({ ...DEFAULTS });
const searchInput = ref('');
const page = ref(1);

const products = ref<AdminProduct[]>([]);
const meta = ref<PageMeta>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const statusCounts = ref<Record<string, number>>({});
const statusCountsTotal = ref(0);
const isLoading = ref(true);
const error = ref<string | null>(null);

let searchTimer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const activeChips = computed(() => {
    const chips: { key: FilterKey; label: string }[] = [];

    if (filters.search) chips.push({ key: 'search', label: `“${filters.search}”` });
    if (filters.seller_id) chips.push({ key: 'seller_id', label: props.sellers.find((item) => String(item.id) === filters.seller_id)?.store_name ?? 'Store' });
    if (filters.category_id) chips.push({ key: 'category_id', label: props.categories.find((item) => String(item.id) === filters.category_id)?.name ?? 'Category' });
    if (filters.stock) chips.push({ key: 'stock', label: STOCK_OPTIONS.find((item) => item.value === filters.stock)?.label ?? filters.stock });

    return chips;
});

function queryString(withPage: boolean): string {
    const params = new URLSearchParams();

    for (const key of FILTER_KEYS) {
        if (filters[key] !== DEFAULTS[key]) params.set(key, filters[key]);
    }
    if (withPage && page.value > 1) params.set('page', String(page.value));

    return params.toString();
}

/** Keep the filters in the address bar so the view can be shared or refreshed. */
function syncUrl(): void {
    const query = queryString(true);
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
}

async function load(): Promise<void> {
    const current = ++requestId;
    isLoading.value = true;
    error.value = null;
    syncUrl();

    try {
        const query = queryString(false);
        const response = await fetch(`/api/v1/admin/products?${query}${query ? '&' : ''}page=${page.value}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error(String(response.status));

        const payload = (await response.json()) as {
            data: AdminProduct[];
            meta: PageMeta;
            status_counts: Record<string, number>;
            status_counts_total: number;
        };

        if (current !== requestId) return;

        products.value = payload.data;
        meta.value = payload.meta;
        statusCounts.value = payload.status_counts ?? {};
        statusCountsTotal.value = payload.status_counts_total ?? 0;
    } catch {
        if (current === requestId) error.value = 'Products are temporarily unavailable.';
    } finally {
        if (current === requestId) isLoading.value = false;
    }
}

function applyFilters(): void {
    page.value = 1;
    void load();
}

function setStatus(status: string): void {
    filters.status = status;
    applyFilters();
}

function clearFilter(key: FilterKey): void {
    filters[key] = DEFAULTS[key];
    if (key === 'search') searchInput.value = '';
    applyFilters();
}

function resetFilters(): void {
    Object.assign(filters, DEFAULTS);
    searchInput.value = '';
    applyFilters();
}

function gotoPage(target: number): void {
    if (target < 1 || target > meta.value.last_page) return;
    page.value = target;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    void load();
}

watch(searchInput, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (value.trim() !== filters.search) {
            filters.search = value.trim();
            applyFilters();
        }
    }, 350);
});

const pageNumbers = computed<(number | '…')[]>(() => {
    const last = meta.value.last_page;
    const current = meta.value.current_page;
    const pages = new Set([1, last, current - 1, current, current + 1].filter((number) => number >= 1 && number <= last));
    const sorted = [...pages].sort((a, b) => a - b);
    const result: (number | '…')[] = [];

    sorted.forEach((number, i) => {
        if (i > 0 && number - sorted[i - 1] > 1) result.push('…');
        result.push(number);
    });

    return result;
});

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    draft: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    inactive: 'bg-muted text-muted-foreground ring-border',
    archived: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
};

function label(value: string): string {
    return value.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function money(value: string | number | undefined): string {
    const amount = Number(value);

    return Number.isFinite(amount) ? formatPrice(amount) : String(value ?? '');
}

function stockClass(stock: number): string {
    if (stock <= 0) return 'text-red-600';
    if (stock <= LOW_STOCK) return 'text-amber-700 dark:text-amber-300';

    return '';
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);

    for (const key of FILTER_KEYS) {
        const value = params.get(key);
        if (value !== null) filters[key] = value;
    }
    searchInput.value = filters.search;
    page.value = Math.max(1, Number(params.get('page') ?? 1) || 1);

    void load();
});

onBeforeUnmount(() => {
    if (searchTimer) clearTimeout(searchTimer);
});

const selectClass = 'h-9 rounded-md border bg-background px-2.5 text-sm text-foreground';
</script>

<template>
    <Head title="Products" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading variant="small" title="Products" description="Search, filter and moderate every product in the marketplace." />

        <section class="rounded-xl border bg-card shadow-sm">
            <!-- Status tabs -->
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Product status">
                <button
                    v-for="tab in STATUS_TABS"
                    :key="tab.value"
                    type="button"
                    class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition"
                    :class="filters.status === tab.value ? 'border-[var(--brand-primary,#ee4d2d)] font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    :aria-pressed="filters.status === tab.value"
                    @click="setStatus(tab.value)"
                >
                    {{ tab.label }}
                    <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">
                        {{ tab.value === '' ? statusCountsTotal : (statusCounts[tab.value] ?? 0) }}
                    </span>
                </button>
            </nav>

            <!-- Toolbar -->
            <div class="flex flex-col gap-3 p-3">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
                    <label class="relative flex-1">
                        <span class="sr-only">Search products</span>
                        <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <input
                            v-model="searchInput"
                            type="search"
                            placeholder="Search product name, slug, store or category"
                            class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm"
                        />
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <select v-model="filters.sort" :class="selectClass" aria-label="Sort" @change="applyFilters">
                            <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                        </select>
                        <select v-model="filters.per_page" :class="selectClass" aria-label="Rows per page" @change="applyFilters">
                            <option v-for="size in ['15', '25', '50', '100']" :key="size" :value="size">{{ size }} / page</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select v-model="filters.seller_id" :class="selectClass" aria-label="Store" @change="applyFilters">
                        <option value="">All stores</option>
                        <option v-for="seller in sellers" :key="seller.id" :value="String(seller.id)">{{ seller.store_name }}</option>
                    </select>
                    <select v-model="filters.category_id" :class="selectClass" aria-label="Category" @change="applyFilters">
                        <option value="">All categories</option>
                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                    </select>
                    <select v-model="filters.stock" :class="selectClass" aria-label="Stock" @change="applyFilters">
                        <option value="">Any stock</option>
                        <option v-for="option in STOCK_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div v-if="activeChips.length" class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="chip in activeChips"
                        :key="chip.key"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full border bg-muted/60 px-2.5 py-0.5 text-xs hover:bg-muted"
                        :aria-label="`Remove filter ${chip.label}`"
                        @click="clearFilter(chip.key)"
                    >
                        {{ chip.label }} <X class="size-3" aria-hidden="true" />
                    </button>
                    <button type="button" class="text-xs font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline" @click="resetFilters">
                        Clear all
                    </button>
                </div>
            </div>

            <!-- Results -->
            <div class="border-t">
                <div v-if="isLoading && products.length === 0" class="animate-pulse space-y-2 p-4">
                    <div v-for="n in 5" :key="n" class="h-12 rounded bg-muted" />
                </div>

                <p v-else-if="error" class="p-4 text-sm text-amber-600">{{ error }}</p>

                <div v-else-if="products.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    No products match these filters.
                    <button type="button" class="ml-1 font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline" @click="resetFilters">
                        Clear filters
                    </button>
                </div>

                <div v-else class="overflow-x-auto" :class="isLoading ? 'opacity-60 transition-opacity' : ''">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Product</th>
                                <th class="px-4 py-2 font-medium">Store</th>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 text-right font-medium">Price</th>
                                <th class="px-4 py-2 text-right font-medium">Stock</th>
                                <th class="px-4 py-2 text-right font-medium">Sold</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in products" :key="product.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <img v-if="product.primary_image" :src="product.primary_image" alt="" class="size-10 shrink-0 rounded-md border object-cover" loading="lazy" />
                                        <span v-else class="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground" aria-hidden="true">
                                            <ImageOff class="size-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <Link :href="show(product.id)" class="block max-w-64 truncate font-medium hover:underline">{{ product.name }}</Link>
                                            <p class="max-w-64 truncate text-xs text-muted-foreground">{{ product.slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2">{{ product.seller?.store_name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ product.category?.name ?? '—' }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap tabular-nums">
                                    <template v-if="product.flash_sale_active">
                                        <span class="font-medium text-[var(--brand-primary,#ee4d2d)]">{{ money(product.effective_price) }}</span>
                                        <span class="block text-xs text-muted-foreground line-through">{{ money(product.price) }}</span>
                                    </template>
                                    <template v-else>{{ money(product.price) }}</template>
                                </td>
                                <td class="px-4 py-2 text-right font-medium tabular-nums" :class="stockClass(product.stock)">
                                    {{ product.stock <= 0 ? 'Out' : product.stock }}
                                </td>
                                <td class="px-4 py-2 text-right text-muted-foreground tabular-nums">{{ product.sold_count ?? 0 }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[product.status] ?? 'bg-muted text-muted-foreground ring-border'">
                                        {{ label(product.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                                        <Link :href="show(product.id)" class="text-sm text-muted-foreground hover:text-foreground">Detail</Link>
                                        <a :href="edit(product.id).url" class="text-sm font-medium hover:underline">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!error && meta.total > 0" class="flex flex-col gap-3 border-t p-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-medium text-foreground">{{ meta.from }}–{{ meta.to }}</span> of
                        <span class="font-medium text-foreground">{{ meta.total }}</span> products
                    </p>
                    <nav v-if="meta.last_page > 1" class="flex flex-wrap items-center gap-1" aria-label="Pagination">
                        <button type="button" :disabled="meta.current_page === 1" class="rounded border px-2.5 py-1 text-xs disabled:opacity-40" @click="gotoPage(meta.current_page - 1)">Previous</button>
                        <template v-for="(number, i) in pageNumbers" :key="`${number}-${i}`">
                            <span v-if="number === '…'" class="px-1.5 text-xs text-muted-foreground">…</span>
                            <button
                                v-else
                                type="button"
                                class="min-w-8 rounded border px-2.5 py-1 text-xs"
                                :class="number === meta.current_page ? 'border-[var(--brand-primary,#ee4d2d)] bg-[var(--brand-primary,#ee4d2d)] text-white' : 'hover:bg-muted'"
                                :aria-current="number === meta.current_page ? 'page' : undefined"
                                @click="gotoPage(number)"
                            >
                                {{ number }}
                            </button>
                        </template>
                        <button type="button" :disabled="meta.current_page === meta.last_page" class="rounded border px-2.5 py-1 text-xs disabled:opacity-40" @click="gotoPage(meta.current_page + 1)">Next</button>
                    </nav>
                </div>
            </div>
        </section>
    </div>
</template>
