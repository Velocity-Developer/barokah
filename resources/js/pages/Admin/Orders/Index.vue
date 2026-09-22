<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Search, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { index, show } from '@/routes/admin/orders';
import { formatPrice } from '@/services/priceFormatter';

type AdminOrderListItem = {
    id: number;
    order_number: string;
    status: string;
    total: string | number;
    customer_name?: string | null;
    customer_phone?: string | null;
    created_at: string;
    items?: { id: number; product_name: string; quantity: number }[];
    payment?: { payment_method: string; status: string } | null;
};

type PageMeta = { current_page: number; last_page: number; total: number; from: number | null; to: number | null };

const props = defineProps<{
    sellers: { id: number; store_name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Orders', href: index() }],
    },
});

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'pending_payment', label: 'Pending payment' },
    { value: 'paid', label: 'Paid' },
    { value: 'processing', label: 'Processing' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
];

const PAYMENT_METHODS = [
    { value: 'fpx', label: 'FPX' },
    { value: 'duitnow', label: 'DuitNow' },
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'qr_code', label: 'QR code' },
];

const PAYMENT_STATUSES = [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'failed', label: 'Failed' },
    { value: 'expired', label: 'Expired' },
    { value: 'cancelled', label: 'Cancelled' },
];

const SORTS = [
    { value: 'latest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
    { value: 'total_desc', label: 'Highest total' },
    { value: 'total_asc', label: 'Lowest total' },
];

const FILTER_KEYS = ['search', 'status', 'payment_method', 'payment_status', 'seller_id', 'date_from', 'date_to', 'sort', 'per_page'] as const;
type FilterKey = (typeof FILTER_KEYS)[number];

const DEFAULTS: Record<FilterKey, string> = {
    search: '',
    status: '',
    payment_method: '',
    payment_status: '',
    seller_id: '',
    date_from: '',
    date_to: '',
    sort: 'latest',
    per_page: '15',
};

const filters = reactive<Record<FilterKey, string>>({ ...DEFAULTS });
const searchInput = ref('');
const page = ref(1);

const orders = ref<AdminOrderListItem[]>([]);
const meta = ref<PageMeta>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const statusCounts = ref<Record<string, number>>({});
const statusCountsTotal = ref(0);
const isLoading = ref(true);
const error = ref<string | null>(null);

let searchTimer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const hasActiveFilters = computed(() =>
    FILTER_KEYS.some((key) => key !== 'sort' && key !== 'per_page' && key !== 'status' && filters[key] !== DEFAULTS[key]),
);

const activeChips = computed(() => {
    const chips: { key: FilterKey; label: string }[] = [];
    const find = (list: { value: string; label: string }[], value: string) => list.find((item) => item.value === value)?.label ?? value;

    if (filters.search) chips.push({ key: 'search', label: `“${filters.search}”` });
    if (filters.payment_method) chips.push({ key: 'payment_method', label: find(PAYMENT_METHODS, filters.payment_method) });
    if (filters.payment_status) chips.push({ key: 'payment_status', label: `Payment ${find(PAYMENT_STATUSES, filters.payment_status).toLowerCase()}` });
    if (filters.seller_id) chips.push({ key: 'seller_id', label: props.sellers.find((seller) => String(seller.id) === filters.seller_id)?.store_name ?? 'Store' });
    if (filters.date_from) chips.push({ key: 'date_from', label: `From ${filters.date_from}` });
    if (filters.date_to) chips.push({ key: 'date_to', label: `To ${filters.date_to}` });

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
        const response = await fetch(`/api/v1/admin/orders?${query}${query ? '&' : ''}page=${page.value}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error(String(response.status));

        const payload = (await response.json()) as {
            data: AdminOrderListItem[];
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
        if (current === requestId) error.value = 'Orders are temporarily unavailable.';
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
    pending_payment: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    paid: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    processing: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    shipped: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-900',
    completed: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
};

function label(value: string | null | undefined): string {
    return (value ?? '').replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function paymentLabel(order: AdminOrderListItem): string {
    const method = PAYMENT_METHODS.find((item) => item.value === order.payment?.payment_method)?.label;

    return method ? `${method} · ${label(order.payment?.status)}` : '—';
}

function displayTotal(total: string | number): string {
    const amount = Number(total);

    return Number.isFinite(amount) ? formatPrice(amount) : String(total);
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function clearEverything(): void {
    filters.status = '';
    resetFilters();
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
    <Head title="Orders" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading variant="small" title="Orders" description="Search, filter and sort every customer order." />

        <section class="rounded-xl border bg-card shadow-sm">
            <!-- Status tabs -->
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Order status">
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
                        <span class="sr-only">Search orders</span>
                        <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <input
                            v-model="searchInput"
                            type="search"
                            placeholder="Search order number, customer, phone, email, product, store or coupon"
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
                    <select v-model="filters.payment_method" :class="selectClass" aria-label="Payment method" @change="applyFilters">
                        <option value="">All payment methods</option>
                        <option v-for="method in PAYMENT_METHODS" :key="method.value" :value="method.value">{{ method.label }}</option>
                    </select>
                    <select v-model="filters.payment_status" :class="selectClass" aria-label="Payment status" @change="applyFilters">
                        <option value="">All payment statuses</option>
                        <option v-for="status in PAYMENT_STATUSES" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                    <select v-model="filters.seller_id" :class="selectClass" aria-label="Store" @change="applyFilters">
                        <option value="">All stores</option>
                        <option v-for="seller in sellers" :key="seller.id" :value="String(seller.id)">{{ seller.store_name }}</option>
                    </select>
                    <label class="flex items-center gap-1.5 text-sm text-muted-foreground">
                        From
                        <input v-model="filters.date_from" type="date" :max="filters.date_to || undefined" :class="selectClass" @change="applyFilters" />
                    </label>
                    <label class="flex items-center gap-1.5 text-sm text-muted-foreground">
                        To
                        <input v-model="filters.date_to" type="date" :min="filters.date_from || undefined" :class="selectClass" @change="applyFilters" />
                    </label>
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
                <div v-if="isLoading && orders.length === 0" class="animate-pulse space-y-2 p-4">
                    <div v-for="n in 5" :key="n" class="h-10 rounded bg-muted" />
                </div>

                <p v-else-if="error" class="p-4 text-sm text-amber-600">{{ error }}</p>

                <div v-else-if="orders.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    No orders match these filters.
                    <button v-if="hasActiveFilters || filters.status" type="button" class="ml-1 font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline" @click="clearEverything">
                        Clear filters
                    </button>
                </div>

                <div v-else class="overflow-x-auto" :class="isLoading ? 'opacity-60 transition-opacity' : ''">
                    <table class="w-full min-w-[880px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Order</th>
                                <th class="px-4 py-2 font-medium">Customer</th>
                                <th class="px-4 py-2 font-medium">Items</th>
                                <th class="px-4 py-2 font-medium">Payment</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 text-right font-medium">Total</th>
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
                                    <p>{{ order.customer_name ?? '—' }}</p>
                                    <p v-if="order.customer_phone" class="text-xs text-muted-foreground">{{ order.customer_phone }}</p>
                                </td>
                                <td class="max-w-56 px-4 py-2.5">
                                    <span class="block truncate">{{ order.items?.[0]?.product_name ?? '—' }}</span>
                                    <span v-if="(order.items?.length ?? 0) > 1" class="text-xs text-muted-foreground">+{{ (order.items?.length ?? 1) - 1 }} more</span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-muted-foreground">{{ paymentLabel(order) }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset" :class="statusStyles[order.status] ?? 'bg-muted text-muted-foreground ring-border'">
                                        {{ label(order.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">{{ displayTotal(order.total) }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <Link :href="show(order.order_number)" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground" :aria-label="`Open ${order.order_number}`">
                                        Open <ArrowRight class="size-3.5" aria-hidden="true" />
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
