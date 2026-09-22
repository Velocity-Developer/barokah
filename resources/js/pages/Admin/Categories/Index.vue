<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { create, edit, index } from '@/routes/admin/categories';
import { index as productsIndex } from '@/routes/admin/products';

type AdminCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    products_count?: number;
    active_products_count?: number;
};

type PageMeta = { current_page: number; last_page: number; total: number; from: number | null; to: number | null };

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categories', href: index() }],
    },
});

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const SORTS = [
    { value: 'position', label: 'Display order' },
    { value: 'name_asc', label: 'Name A–Z' },
    { value: 'name_desc', label: 'Name Z–A' },
    { value: 'products_desc', label: 'Most products' },
    { value: 'newest', label: 'Newest first' },
];

const FILTER_KEYS = ['search', 'status', 'sort', 'per_page'] as const;
type FilterKey = (typeof FILTER_KEYS)[number];
const DEFAULTS: Record<FilterKey, string> = { search: '', status: '', sort: 'position', per_page: '15' };

const filters = reactive<Record<FilterKey, string>>({ ...DEFAULTS });
const searchInput = ref('');
const page = ref(1);

const categories = ref<AdminCategory[]>([]);
const meta = ref<PageMeta>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const statusCounts = ref<Record<string, number>>({});
const statusCountsTotal = ref(0);
const isLoading = ref(true);
const error = ref<string | null>(null);
const busyId = ref<number | null>(null);

let searchTimer: ReturnType<typeof setTimeout> | null = null;
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
        const response = await fetch(`/api/v1/admin/categories?${query}${query ? '&' : ''}page=${page.value}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error(String(response.status));

        const payload = (await response.json()) as {
            data: AdminCategory[];
            meta: PageMeta;
            status_counts: Record<string, number>;
            status_counts_total: number;
        };

        if (current !== requestId) return;

        categories.value = payload.data;
        meta.value = payload.meta;
        statusCounts.value = payload.status_counts ?? {};
        statusCountsTotal.value = payload.status_counts_total ?? 0;
    } catch {
        if (current === requestId) error.value = 'Categories are temporarily unavailable.';
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

watch(searchInput, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (value.trim() !== filters.search) {
            filters.search = value.trim();
            applyFilters();
        }
    }, 350);
});

async function toggleActive(category: AdminCategory): Promise<void> {
    busyId.value = category.id;
    const next = !category.is_active;

    try {
        const response = await fetch(`/api/v1/admin/categories/${category.id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ is_active: next }),
        });

        if (!response.ok) throw new Error();

        toast.success(`${category.name} is now ${next ? 'active' : 'hidden from the store'}.`);
        await load();
    } catch {
        toast.error('Category could not be updated.');
    } finally {
        busyId.value = null;
    }
}

async function remove(category: AdminCategory): Promise<void> {
    if (!window.confirm(`Delete category "${category.name}"? This cannot be undone.`)) return;

    busyId.value = category.id;

    try {
        const response = await fetch(`/api/v1/admin/categories/${category.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });

        if (!response.ok) {
            const payload = (await response.json().catch(() => ({}))) as { message?: string };
            toast.error(payload.message ?? 'Category could not be deleted.');
            return;
        }

        toast.success(`${category.name} deleted.`);
        await load();
    } catch {
        toast.error('Category could not be deleted.');
    } finally {
        busyId.value = null;
    }
}

const pageNumbers = computed<number[]>(() => Array.from({ length: meta.value.last_page }, (_, i) => i + 1));

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
    <Head title="Categories" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading variant="small" title="Categories" description="Group products and set the order they appear in the store." />
            <Link :href="create()" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                <Plus class="size-4" aria-hidden="true" /> New category
            </Link>
        </div>

        <section class="rounded-xl border bg-card shadow-sm">
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Category status">
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

            <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
                <label class="relative flex-1">
                    <span class="sr-only">Search categories</span>
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input v-model="searchInput" type="search" placeholder="Search name, slug or description" class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm" />
                </label>
                <select v-model="filters.sort" :class="selectClass" aria-label="Sort" @change="applyFilters">
                    <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                </select>
            </div>

            <div class="border-t">
                <div v-if="isLoading && categories.length === 0" class="animate-pulse space-y-2 p-4">
                    <div v-for="n in 3" :key="n" class="h-12 rounded bg-muted" />
                </div>

                <p v-else-if="error" class="p-4 text-sm text-amber-600">{{ error }}</p>

                <div v-else-if="categories.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    <template v-if="filters.search || filters.status">
                        No categories match these filters.
                        <button type="button" class="ml-1 font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline" @click="resetFilters">Clear filters</button>
                    </template>
                    <template v-else>No categories yet. Create the first one to start grouping products.</template>
                </div>

                <div v-else class="overflow-x-auto" :class="isLoading ? 'opacity-60 transition-opacity' : ''">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="w-16 px-4 py-2 font-medium">Order</th>
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th class="px-4 py-2 text-right font-medium">Products</th>
                                <th class="px-4 py-2 font-medium">Visible in store</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="category in categories" :key="category.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-3 tabular-nums text-muted-foreground">{{ category.sort_order }}</td>
                                <td class="px-4 py-3">
                                    <Link :href="edit(category.id)" class="font-medium hover:underline">{{ category.name }}</Link>
                                    <p class="text-xs text-muted-foreground">/{{ category.slug }}</p>
                                    <p v-if="category.description" class="mt-0.5 line-clamp-1 max-w-md text-xs text-muted-foreground">{{ category.description }}</p>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <Link :href="productsIndex({ query: { category_id: category.id } })" class="font-medium tabular-nums hover:underline">
                                        {{ category.products_count ?? 0 }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">{{ category.active_products_count ?? 0 }} active</p>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="category.is_active"
                                        :aria-label="`${category.is_active ? 'Hide' : 'Show'} ${category.name} in the store`"
                                        :disabled="busyId === category.id"
                                        class="inline-flex items-center gap-2 text-xs disabled:opacity-50"
                                        @click="toggleActive(category)"
                                    >
                                        <span class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition" :class="category.is_active ? 'bg-green-600' : 'bg-muted-foreground/30'">
                                            <span class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-all" :class="category.is_active ? 'left-[18px]' : 'left-0.5'" />
                                        </span>
                                        {{ category.is_active ? 'Active' : 'Hidden' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="edit(category.id)" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`Edit ${category.name}`">
                                            <Pencil class="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-muted-foreground"
                                            :disabled="busyId === category.id || (category.products_count ?? 0) > 0"
                                            :title="(category.products_count ?? 0) > 0 ? 'Move or remove its products before deleting' : `Delete ${category.name}`"
                                            :aria-label="`Delete ${category.name}`"
                                            @click="remove(category)"
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
                        <span class="font-medium text-foreground">{{ meta.total }}</span> categories
                    </p>
                    <nav v-if="meta.last_page > 1" class="flex flex-wrap gap-1" aria-label="Pagination">
                        <button
                            v-for="number in pageNumbers"
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
