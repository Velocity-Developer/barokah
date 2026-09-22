<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Eye, MapPin, Pencil, Search, Star, UserCheck } from '@lucide/vue';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import malaysiaStates from '@/data/malaysia-states.json';
import { index as sellerApprovalsIndex } from '@/routes/admin/seller-approvals';
import { edit, index, show } from '@/routes/admin/sellers';

type AdminSeller = {
    id: number;
    store_name: string;
    slug: string;
    status: 'active' | 'pending' | 'suspended';
    profile_photo_url: string | null;
    city: string | null;
    state: string | null;
    products_count?: number;
    active_products_count?: number;
    followers_count?: number;
    average_rating?: number | string | null;
    ratings_count?: number;
    owner?: { name: string; email: string } | null;
    created_at?: string;
};

type PageMeta = { current_page: number; last_page: number; total: number; from: number | null; to: number | null };

defineOptions({ layout: { breadcrumbs: [{ title: 'Stores', href: index() }] } });

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'pending', label: 'Pending' },
    { value: 'suspended', label: 'Suspended' },
];

const SORTS = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'name_asc', label: 'Name A–Z' },
    { value: 'products_desc', label: 'Most products' },
    { value: 'followers_desc', label: 'Most followers' },
];

const stateOptions = (malaysiaStates as { name: string }[]).map((state) => state.name);

const FILTER_KEYS = ['search', 'status', 'state', 'sort', 'per_page'] as const;
type FilterKey = (typeof FILTER_KEYS)[number];
const DEFAULTS: Record<FilterKey, string> = { search: '', status: '', state: '', sort: 'newest', per_page: '15' };

const filters = reactive<Record<FilterKey, string>>({ ...DEFAULTS });
const searchInput = ref('');
const page = ref(1);
const sellers = ref<AdminSeller[]>([]);
const meta = ref<PageMeta>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const statusCounts = ref<Record<string, number>>({});
const statusCountsTotal = ref(0);
const isLoading = ref(true);
const error = ref<string | null>(null);

let timer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

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
        const response = await fetch(`/api/v1/admin/sellers?${query}${query ? '&' : ''}page=${page.value}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error(String(response.status));

        const payload = (await response.json()) as { data: AdminSeller[]; meta: PageMeta; status_counts: Record<string, number>; status_counts_total: number };

        if (current !== requestId) return;

        sellers.value = payload.data;
        meta.value = payload.meta;
        statusCounts.value = payload.status_counts ?? {};
        statusCountsTotal.value = payload.status_counts_total ?? 0;
    } catch {
        if (current === requestId) error.value = 'Stores are temporarily unavailable.';
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

watch(searchInput, (value) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        if (value.trim() !== filters.search) {
            filters.search = value.trim();
            applyFilters();
        }
    }, 350);
});

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
    if (timer) clearTimeout(timer);
});

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    suspended: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
};

function label(status: string): string {
    return STATUS_TABS.find((tab) => tab.value === status)?.label ?? status;
}

const selectClass = 'h-9 rounded-md border bg-background px-2.5 text-sm';
</script>

<template>
    <Head title="Stores" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading variant="small" title="Stores" description="Every store on the marketplace: owners, products and standing." />

        <Link
            v-if="(statusCounts.pending ?? 0) > 0"
            :href="sellerApprovalsIndex()"
            class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
        >
            <span class="flex items-center gap-2">
                <UserCheck class="size-4" aria-hidden="true" />
                {{ statusCounts.pending }} {{ statusCounts.pending === 1 ? 'store is' : 'stores are' }} waiting for approval.
            </span>
            <span class="inline-flex items-center gap-1 font-medium">Review <ArrowRight class="size-3.5" aria-hidden="true" /></span>
        </Link>

        <section class="rounded-xl border bg-card shadow-sm">
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Store status">
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
                    <span class="sr-only">Search stores</span>
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input v-model="searchInput" type="search" placeholder="Search store, owner, email, phone or city" class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm" />
                </label>
                <div class="flex flex-wrap gap-2">
                    <select v-model="filters.state" :class="selectClass" aria-label="State" @change="applyFilters">
                        <option value="">All states</option>
                        <option v-for="state in stateOptions" :key="state" :value="state">{{ state }}</option>
                    </select>
                    <select v-model="filters.sort" :class="selectClass" aria-label="Sort" @change="applyFilters">
                        <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                    </select>
                </div>
            </div>

            <div class="border-t">
                <div v-if="isLoading && sellers.length === 0" class="animate-pulse space-y-2 p-4">
                    <div v-for="n in 3" :key="n" class="h-12 rounded bg-muted" />
                </div>
                <p v-else-if="error" class="p-4 text-sm text-amber-600">{{ error }}</p>
                <div v-else-if="sellers.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    No stores match these filters.
                    <button type="button" class="ml-1 font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline" @click="resetFilters">Clear filters</button>
                </div>

                <div v-else class="overflow-x-auto" :class="isLoading ? 'opacity-60 transition-opacity' : ''">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Store</th>
                                <th class="px-4 py-2 font-medium">Owner</th>
                                <th class="px-4 py-2 text-right font-medium">Products</th>
                                <th class="px-4 py-2 text-right font-medium">Followers</th>
                                <th class="px-4 py-2 font-medium">Rating</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="seller in sellers" :key="seller.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-3">
                                        <img v-if="seller.profile_photo_url" :src="seller.profile_photo_url" alt="" class="size-10 shrink-0 rounded-full border object-cover" />
                                        <span v-else class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--accent-navy,#113366)] text-sm font-semibold text-white" aria-hidden="true">
                                            {{ seller.store_name.charAt(0).toUpperCase() }}
                                        </span>
                                        <div class="min-w-0">
                                            <Link :href="show(seller.id)" class="block max-w-56 truncate font-medium hover:underline">{{ seller.store_name }}</Link>
                                            <p v-if="seller.city || seller.state" class="flex items-center gap-1 text-xs text-muted-foreground">
                                                <MapPin class="size-3" aria-hidden="true" />{{ [seller.city, seller.state].filter(Boolean).join(', ') }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <p class="max-w-48 truncate">{{ seller.owner?.name ?? '—' }}</p>
                                    <p class="max-w-48 truncate text-xs text-muted-foreground">{{ seller.owner?.email }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {{ seller.products_count ?? 0 }}
                                    <span class="block text-xs text-muted-foreground">{{ seller.active_products_count ?? 0 }} active</span>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ seller.followers_count ?? 0 }}</td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <template v-if="seller.ratings_count">
                                        <Star class="mr-0.5 inline size-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />{{ Number(seller.average_rating ?? 0).toFixed(1) }}
                                        <span class="text-xs text-muted-foreground">({{ seller.ratings_count }})</span>
                                    </template>
                                    <span v-else class="text-xs text-muted-foreground">No reviews</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[seller.status] ?? 'bg-muted text-muted-foreground ring-border'">{{ label(seller.status) }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="show(seller.id)" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`View ${seller.store_name}`">
                                            <Eye class="size-4" />
                                        </Link>
                                        <Link :href="edit(seller.id)" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`Edit ${seller.store_name}`">
                                            <Pencil class="size-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!error && meta.total > 0" class="flex flex-col gap-3 border-t p-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-medium text-foreground">{{ meta.from }}–{{ meta.to }}</span> of
                        <span class="font-medium text-foreground">{{ meta.total }}</span> stores
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
