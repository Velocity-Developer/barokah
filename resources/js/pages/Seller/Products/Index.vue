<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, ImageOff, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { formatPrice } from '@/services/priceFormatter';
import { create, edit, index } from '@/routes/seller/products';

type SellerProduct = {
    id: number;
    name: string;
    slug: string;
    status: string;
    price: string | number;
    effective_price?: string | number;
    flash_sale_active?: boolean;
    stock: number;
    category?: { id: number; name: string } | null;
    primary_image?: string | null;
    images?: { url: string; is_primary?: boolean }[];
};

type Category = { id: number; name: string };

type PageMeta = { current_page: number; last_page: number; total: number; from: number | null; to: number | null };

defineOptions({ layout: { breadcrumbs: [{ title: 'Products', href: index() }] } });

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'draft', label: 'Draft' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'archived', label: 'Archived' },
];

const SORTS = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'name_asc', label: 'Name A–Z' },
    { value: 'price_desc', label: 'Highest price' },
    { value: 'price_asc', label: 'Lowest price' },
    { value: 'stock_asc', label: 'Lowest stock' },
];

const LOW_STOCK = 5;

const FILTER_KEYS = ['search', 'status', 'category_id', 'sort', 'per_page'] as const;
type FilterKey = (typeof FILTER_KEYS)[number];
const DEFAULTS: Record<FilterKey, string> = { search: '', status: '', category_id: '', sort: 'newest', per_page: '15' };

const filters = reactive<Record<FilterKey, string>>({ ...DEFAULTS });
const searchInput = ref('');
const page = ref(1);
const products = ref<SellerProduct[]>([]);
const categories = ref<Category[]>([]);
const meta = ref<PageMeta>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const statusCounts = ref<Record<string, number>>({});
const statusCountsTotal = ref(0);
const isLoading = ref(true);
const error = ref<string | null>(null);
const deletingId = ref<number | null>(null);

let timer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function queryString(withPage: boolean): string {
    const params = new URLSearchParams();

    for (const key of FILTER_KEYS) {
        if (filters[key] !== DEFAULTS[key]) params.set(key, filters[key]);
    }
    if (withPage && page.value > 1) params.set('page', String(page.value));

    return params.toString();
}

async function load(): Promise<void> {
    const current = ++requestId;
    isLoading.value = true;
    error.value = null;

    const urlQuery = queryString(true);
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${urlQuery ? `?${urlQuery}` : ''}`);

    try {
        const query = queryString(false);
        const response = await fetch(`/api/v1/seller/products?${query}${query ? '&' : ''}page=${page.value}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) throw new Error(String(response.status));

        const payload = (await response.json()) as {
            data: SellerProduct[];
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

function resetFilters(): void {
    Object.assign(filters, DEFAULTS);
    searchInput.value = '';
    applyFilters();
}

function gotoPage(target: number): void {
    if (target < 1 || target > meta.value.last_page) return;
    page.value = target;
    void load();
}

async function removeProduct(product: SellerProduct): Promise<void> {
    if (!window.confirm(`Delete "${product.name}"? Past orders keep their own copy of the product details.`)) {
        return;
    }

    deletingId.value = product.id;

    try {
        const response = await fetch(`/api/v1/seller/products/${product.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
        });

        if (!response.ok) {
            const body = (await response.json().catch(() => ({}))) as { message?: string };
            toast.error(body.message ?? 'This product could not be deleted.');

            return;
        }

        toast.success(`${product.name} deleted.`);

        // Step back a page when the last row of the page goes away.
        if (products.value.length === 1 && page.value > 1) page.value -= 1;
        await load();
    } catch {
        toast.error('Products are temporarily unavailable.');
    } finally {
        deletingId.value = null;
    }
}

watch(searchInput, (value) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        if (value.trim() !== filters.search) {
            filters.search = value.trim();
            applyFilters();
        }
    }, 350);
});

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);

    for (const key of FILTER_KEYS) {
        const value = params.get(key);
        if (value !== null) filters[key] = value;
    }
    searchInput.value = filters.search;
    page.value = Math.max(1, Number(params.get('page') ?? 1) || 1);
    void load();

    const response = await fetch('/api/v1/categories', { headers: { Accept: 'application/json' } });
    if (response.ok) categories.value = ((await response.json()) as { data: Category[] }).data;
});

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    draft: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    inactive: 'bg-muted text-muted-foreground ring-border',
    archived: 'bg-muted text-muted-foreground ring-border',
};

function statusLabel(status: string): string {
    return STATUS_TABS.find((tab) => tab.value === status)?.label ?? status;
}

function thumbnail(product: SellerProduct): string | null {
    return product.primary_image ?? product.images?.find((image) => image.is_primary)?.url ?? product.images?.[0]?.url ?? null;
}

const selectClass = 'h-9 rounded-md border bg-background px-2.5 text-sm';
</script>

<template>
    <Head title="Products" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading variant="small" title="Products" description="Everything your store sells. Only active products appear on the storefront." />
            <Link :href="create()" class="inline-flex h-9 w-fit items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                <Plus class="size-4" aria-hidden="true" /> Add product
            </Link>
        </div>

        <section class="rounded-xl border bg-card shadow-sm">
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Product status">
                <button
                    v-for="tab in STATUS_TABS"
                    :key="tab.value"
                    type="button"
                    class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition"
                    :class="filters.status === tab.value ? 'border-[var(--brand-primary,#ee4d2d)] font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    :aria-pressed="filters.status === tab.value"
                    @click="filters.status = tab.value; applyFilters()"
                >
                    {{ tab.label }}
                    <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">
                        {{ tab.value === '' ? statusCountsTotal : (statusCounts[tab.value] ?? 0) }}
                    </span>
                </button>
            </nav>

            <div class="flex flex-col gap-2 p-3 lg:flex-row lg:items-center">
                <label class="relative flex-1">
                    <span class="sr-only">Search products</span>
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input v-model="searchInput" type="search" placeholder="Search product name or URL" class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm" />
                </label>
                <div class="flex flex-wrap gap-2">
                    <select v-model="filters.category_id" :class="selectClass" aria-label="Category" @change="applyFilters">
                        <option value="">All categories</option>
                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                    </select>
                    <select v-model="filters.sort" :class="selectClass" aria-label="Sort" @change="applyFilters">
                        <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                    </select>
                </div>
            </div>

            <div class="border-t">
                <div v-if="isLoading && products.length === 0" class="animate-pulse space-y-2 p-4">
                    <div v-for="n in 3" :key="n" class="h-12 rounded bg-muted" />
                </div>
                <p v-else-if="error" class="p-4 text-sm text-amber-600">{{ error }}</p>
                <div v-else-if="products.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    <template v-if="statusCountsTotal === 0">
                        You have no products yet.
                        <Link :href="create()" class="ml-1 font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">Add your first product</Link>
                    </template>
                    <template v-else>
                        No products match these filters.
                        <button type="button" class="ml-1 font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline" @click="resetFilters">Clear filters</button>
                    </template>
                </div>

                <div v-else class="overflow-x-auto" :class="isLoading ? 'opacity-60 transition-opacity' : ''">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Product</th>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 text-right font-medium">Price</th>
                                <th class="px-4 py-2 text-right font-medium">Stock</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in products" :key="product.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-3">
                                        <img v-if="thumbnail(product)" :src="thumbnail(product)!" alt="" class="size-10 shrink-0 rounded border object-cover" />
                                        <span v-else class="flex size-10 shrink-0 items-center justify-center rounded border bg-muted text-muted-foreground" aria-hidden="true">
                                            <ImageOff class="size-4" />
                                        </span>
                                        <div class="min-w-0">
                                            <Link :href="edit(product.id)" class="block max-w-64 truncate font-medium hover:underline">{{ product.name }}</Link>
                                            <p class="max-w-64 truncate text-xs text-muted-foreground">/{{ product.slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">{{ product.category?.name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                    <template v-if="product.flash_sale_active">
                                        <span class="font-medium text-[var(--brand-primary,#ee4d2d)]">{{ formatPrice(Number(product.effective_price ?? product.price)) }}</span>
                                        <span class="block text-xs text-muted-foreground line-through">{{ formatPrice(Number(product.price)) }}</span>
                                    </template>
                                    <template v-else>{{ formatPrice(Number(product.price)) }}</template>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    <span :class="product.stock === 0 ? 'font-medium text-red-600' : product.stock <= LOW_STOCK ? 'font-medium text-amber-700 dark:text-amber-300' : ''">
                                        {{ product.stock }}
                                    </span>
                                    <span v-if="product.stock === 0" class="block text-xs text-red-600">Out of stock</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[product.status] ?? 'bg-muted text-muted-foreground ring-border'">
                                        {{ statusLabel(product.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <a
                                            v-if="product.status === 'active'"
                                            :href="`/products/${product.slug}`"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                            :aria-label="`View ${product.name} on the storefront`"
                                        >
                                            <ExternalLink class="size-4" />
                                        </a>
                                        <Link :href="edit(product.id)" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`Edit ${product.name}`">
                                            <Pencil class="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-red-50 hover:text-red-700 disabled:opacity-50 dark:hover:bg-red-950 dark:hover:text-red-400"
                                            :disabled="deletingId === product.id"
                                            :aria-label="`Delete ${product.name}`"
                                            @click="removeProduct(product)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
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
                    <nav v-if="meta.last_page > 1" class="flex flex-wrap gap-1" aria-label="Pagination">
                        <button
                            v-for="number in meta.last_page"
                            :key="number"
                            type="button"
                            class="min-w-8 rounded border px-2.5 py-1 text-xs"
                            :class="number === meta.current_page ? 'border-[var(--brand-primary,#ee4d2d)] bg-[var(--brand-primary,#ee4d2d)] text-white' : 'hover:bg-muted'"
                            @click="gotoPage(number)"
                        >
                            {{ number }}
                        </button>
                    </nav>
                </div>
            </div>
        </section>
    </div>
</template>
