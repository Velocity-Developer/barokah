<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Search } from '@lucide/vue';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { formatPrice } from '@/services/priceFormatter';
import { index as ordersIndex, show } from '@/routes/seller/orders';

type SellerOrder = {
    id: number;
    order_number: string;
    status: string;
    tracking_status?: string | null;
    payment?: { status?: string | null; payment_method?: string | null } | null;
    customer_name: string;
    customer_city?: string | null;
    customer_state?: string | null;
    first_item?: string | null;
    items_count?: number;
    subtotal: string | number;
    total: string | number;
    created_at: string;
};

type PageMeta = { current_page: number; last_page: number; total: number; from: number | null; to: number | null };

defineOptions({ layout: { breadcrumbs: [{ title: 'Customer orders', href: ordersIndex() }] } });

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'pending_payment', label: 'Awaiting payment' },
    { value: 'paid', label: 'Paid' },
    { value: 'processing', label: 'Processing' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
];

const FULFILMENTS = [
    { value: '', label: 'Any handover' },
    { value: 'not_started', label: 'Not started' },
    { value: 'received', label: 'Received' },
    { value: 'packed', label: 'Packed' },
    { value: 'picked_up', label: 'In transit' },
    { value: 'delivered', label: 'Delivered' },
];

const SORTS = [
    { value: 'latest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'total_desc', label: 'Highest total' },
    { value: 'total_asc', label: 'Lowest total' },
];

const FILTER_KEYS = ['search', 'status', 'fulfilment', 'sort', 'per_page'] as const;
type FilterKey = (typeof FILTER_KEYS)[number];
const DEFAULTS: Record<FilterKey, string> = { search: '', status: '', fulfilment: '', sort: 'latest', per_page: '15' };

const filters = reactive<Record<FilterKey, string>>({ ...DEFAULTS });
const searchInput = ref('');
const page = ref(1);
const orders = ref<SellerOrder[]>([]);
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
        const response = await fetch(`/api/v1/seller/orders?${query}${query ? '&' : ''}page=${page.value}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error(String(response.status));

        const payload = (await response.json()) as {
            data: SellerOrder[];
            meta: PageMeta;
            status_counts: Record<string, number>;
            status_counts_total: number;
        };

        if (current !== requestId) return;

        orders.value = payload.data;
        meta.value = payload.meta;
        statusCounts.value = payload.status_counts ?? {};
        statusCountsTotal.value = payload.status_counts_total ?? 0;
    } catch {
        if (current === requestId) error.value = 'Customer orders are temporarily unavailable.';
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
    pending_payment: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    paid: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    processing: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    shipped: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-900',
    completed: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    cancelled: 'bg-muted text-muted-foreground ring-border',
    expired: 'bg-muted text-muted-foreground ring-border',
};

function statusLabel(status: string): string {
    return STATUS_TABS.find((tab) => tab.value === status)?.label ?? status.replaceAll('_', ' ');
}

function fulfilmentLabel(status: string | null | undefined): string {
    return FULFILMENTS.find((item) => item.value === (status ?? ''))?.label ?? status ?? 'Not started';
}

function paymentLabel(order: SellerOrder): string {
    const method = (order.payment?.payment_method ?? '').replaceAll('_', ' ');
    const status = (order.payment?.status ?? '').replaceAll('_', ' ');

    return method ? `${method} · ${status}` : '—';
}

function money(value: string | number): string {
    const amount = Number(value);

    return Number.isFinite(amount) ? formatPrice(amount) : String(value);
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const selectClass = 'h-9 rounded-md border bg-background px-2.5 text-sm';
</script>

<template>
    <Head title="Customer orders" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading variant="small" title="Customer orders" description="Every order that contains your products. Totals count your items only." />

        <section class="rounded-xl border bg-card shadow-sm">
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Order status">
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
                    <span class="sr-only">Search orders</span>
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input v-model="searchInput" type="search" placeholder="Search order number, customer or product" class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm" />
                </label>
                <div class="flex flex-wrap gap-2">
                    <select v-model="filters.fulfilment" :class="selectClass" aria-label="Handover" @change="applyFilters">
                        <option v-for="option in FULFILMENTS" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <select v-model="filters.sort" :class="selectClass" aria-label="Sort" @change="applyFilters">
                        <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                    </select>
                </div>
            </div>

            <div class="border-t">
                <div v-if="isLoading && orders.length === 0" class="animate-pulse space-y-2 p-4">
                    <div v-for="n in 3" :key="n" class="h-12 rounded bg-muted" />
                </div>
                <p v-else-if="error" class="p-4 text-sm text-amber-600">{{ error }}</p>
                <div v-else-if="orders.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    No orders match these filters.
                    <button type="button" class="ml-1 font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline" @click="resetFilters">Clear filters</button>
                </div>

                <div v-else class="overflow-x-auto" :class="isLoading ? 'opacity-60 transition-opacity' : ''">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Order</th>
                                <th class="px-4 py-2 font-medium">Customer</th>
                                <th class="px-4 py-2 font-medium">Your items</th>
                                <th class="px-4 py-2 font-medium">Payment</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 font-medium">Handover</th>
                                <th class="px-4 py-2 text-right font-medium">Your total</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in orders" :key="order.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2.5">
                                    <Link :href="show(order.order_number)" class="font-medium hover:underline">{{ order.order_number }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ formatDate(order.created_at) }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <p class="max-w-44 truncate">{{ order.customer_name }}</p>
                                    <p v-if="order.customer_city || order.customer_state" class="max-w-44 truncate text-xs text-muted-foreground">
                                        {{ [order.customer_city, order.customer_state].filter(Boolean).join(', ') }}
                                    </p>
                                </td>
                                <td class="max-w-56 px-4 py-2.5">
                                    <span class="block truncate">{{ order.first_item ?? '—' }}</span>
                                    <span v-if="(order.items_count ?? 0) > 1" class="text-xs text-muted-foreground">+{{ (order.items_count ?? 1) - 1 }} more</span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-muted-foreground capitalize">{{ paymentLabel(order) }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[order.status] ?? 'bg-muted text-muted-foreground ring-border'">
                                        {{ statusLabel(order.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-muted-foreground">{{ fulfilmentLabel(order.tracking_status) }}</td>
                                <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ money(order.total) }}</td>
                                <td class="px-4 py-2.5">
                                    <Link :href="show(order.order_number)" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`Open ${order.order_number}`">
                                        <Eye class="size-4" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!error && meta.total > 0" class="flex flex-col gap-3 border-t p-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-medium text-foreground">{{ meta.from }}–{{ meta.to }}</span> of
                        <span class="font-medium text-foreground">{{ meta.total }}</span> orders
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
