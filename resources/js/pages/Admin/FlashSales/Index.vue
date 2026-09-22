<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ImageOff, Pencil, Plus, Search, Square, Trash2 } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { create as createFlashSale, edit as editFlashSale, index } from '@/routes/admin/flash-sales';

type Sale = {
    id: number;
    product: { id: number; name: string; image: string | null; store: string | null } | null;
    price_formatted: string;
    normal_price_formatted: string | null;
    discount_label: string;
    quantity: number;
    quantity_sold: number;
    starts_at: string;
    ends_at: string;
    status: 'active' | 'scheduled' | 'sold_out' | 'ended';
};

type Paginated = {
    data: Sale[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    flashSales: Paginated;
    filters: { search: string; status: string; sort: string };
    statusCounts: Record<string, number>;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Flash sales', href: index() }] } });

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Running' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'sold_out', label: 'Sold out' },
    { value: 'ended', label: 'Ended' },
];

const SORTS = [
    { value: 'starts_desc', label: 'Latest start' },
    { value: 'starts_asc', label: 'Earliest start' },
    { value: 'ends_asc', label: 'Ending soonest' },
    { value: 'sold_desc', label: 'Most sold' },
];

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    scheduled: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    sold_out: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    ended: 'bg-muted text-muted-foreground ring-border',
};

const search = ref(props.filters.search);
const busyId = ref<number | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;

function visit(overrides: Partial<typeof props.filters> = {}): void {
    const query = { ...props.filters, search: search.value.trim(), ...overrides };
    const clean = Object.fromEntries(Object.entries(query).filter(([key, value]) => value !== '' && !(key === 'sort' && value === 'starts_desc')));

    router.get(index().url, clean, { preserveState: true, preserveScroll: true, replace: true });
}

watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => visit(), 350);
});

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});

function label(status: string): string {
    return STATUS_TABS.find((tab) => tab.value === status)?.label ?? status;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function soldPercent(sale: Sale): number {
    return sale.quantity > 0 ? Math.min(100, Math.round((sale.quantity_sold / sale.quantity) * 100)) : 0;
}

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

/** Scheduled sales are deleted; running ones are ended now (sold units stay recorded). */
async function stop(sale: Sale): Promise<void> {
    const started = sale.status !== 'scheduled';
    const question = started ? `End the flash sale for "${sale.product?.name}" now?` : `Delete the scheduled flash sale for "${sale.product?.name}"?`;

    if (!window.confirm(question)) return;

    busyId.value = sale.id;

    try {
        const response = await fetch(`/api/v1/admin/flash-sales/${sale.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) throw new Error();

        toast.success(started ? 'Flash sale ended.' : 'Flash sale deleted.');
        router.reload({ only: ['flashSales', 'statusCounts'] });
    } catch {
        toast.error('Flash sale could not be updated.');
    } finally {
        busyId.value = null;
    }
}

const total = () => Object.values(props.statusCounts).reduce((sum, count) => sum + count, 0);
</script>

<template>
    <Head title="Flash sales" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading variant="small" title="Flash sales" description="Time-limited promo prices on products from every store." />
            <Link :href="createFlashSale()" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                <Plus class="size-4" aria-hidden="true" /> New flash sale
            </Link>
        </div>

        <section class="rounded-xl border bg-card shadow-sm">
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Flash sale status">
                <button
                    v-for="tab in STATUS_TABS"
                    :key="tab.value"
                    type="button"
                    class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition"
                    :class="filters.status === tab.value ? 'border-[var(--brand-primary,#ee4d2d)] font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    :aria-pressed="filters.status === tab.value"
                    @click="visit({ status: tab.value })"
                >
                    {{ tab.label }}
                    <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">
                        {{ tab.value === '' ? total() : (statusCounts[tab.value] ?? 0) }}
                    </span>
                </button>
            </nav>

            <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
                <label class="relative flex-1">
                    <span class="sr-only">Search flash sales</span>
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input v-model="search" type="search" placeholder="Search product or store" class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm" />
                </label>
                <select :value="filters.sort" class="h-9 rounded-md border bg-background px-2.5 text-sm" aria-label="Sort" @change="visit({ sort: ($event.target as HTMLSelectElement).value })">
                    <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                </select>
            </div>

            <div class="border-t">
                <div v-if="flashSales.data.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    <template v-if="filters.search || filters.status">No flash sales match these filters.</template>
                    <template v-else>No flash sales yet. Create one to promote a product for a limited time.</template>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Product</th>
                                <th class="px-4 py-2 text-right font-medium">Promo price</th>
                                <th class="px-4 py-2 font-medium">Sold / quota</th>
                                <th class="px-4 py-2 font-medium">Period</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="sale in flashSales.data" :key="sale.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-3">
                                        <img v-if="sale.product?.image" :src="sale.product.image" alt="" class="size-10 shrink-0 rounded-md border object-cover" loading="lazy" />
                                        <span v-else class="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground" aria-hidden="true"><ImageOff class="size-4" /></span>
                                        <div class="min-w-0">
                                            <p class="max-w-64 truncate font-medium">{{ sale.product?.name ?? '—' }}</p>
                                            <p class="text-xs text-muted-foreground">{{ sale.product?.store ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap tabular-nums">
                                    <span class="font-medium text-[var(--brand-primary,#ee4d2d)]">{{ sale.price_formatted }}</span>
                                    <span class="block text-xs text-muted-foreground"><span class="line-through">{{ sale.normal_price_formatted }}</span> · {{ sale.discount_label }}</span>
                                </td>
                                <td class="w-40 px-4 py-2.5">
                                    <p class="text-xs tabular-nums">{{ sale.quantity_sold }} / {{ sale.quantity }}</p>
                                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted" role="progressbar" :aria-valuenow="soldPercent(sale)" aria-valuemin="0" aria-valuemax="100" :aria-label="`${soldPercent(sale)}% sold`">
                                        <div class="h-full rounded-full bg-[var(--brand-primary,#ee4d2d)]" :style="{ width: `${soldPercent(sale)}%` }" />
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-xs whitespace-nowrap">
                                    <p>{{ formatDate(sale.starts_at) }}</p>
                                    <p class="text-muted-foreground">to {{ formatDate(sale.ends_at) }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset" :class="statusStyles[sale.status]">{{ label(sale.status) }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            v-if="sale.status !== 'ended'"
                                            :href="editFlashSale(sale.id)"
                                            class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                            :aria-label="`Edit flash sale for ${sale.product?.name}`"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                        <button
                                            v-if="sale.status !== 'ended'"
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-red-50 hover:text-red-600 disabled:opacity-40"
                                            :disabled="busyId === sale.id"
                                            :title="sale.status === 'scheduled' ? 'Delete' : 'End now'"
                                            :aria-label="sale.status === 'scheduled' ? `Delete flash sale for ${sale.product?.name}` : `End flash sale for ${sale.product?.name}`"
                                            @click="stop(sale)"
                                        >
                                            <Trash2 v-if="sale.status === 'scheduled'" class="size-4" />
                                            <Square v-else class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="flashSales.total > 0" class="flex flex-col gap-3 border-t p-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-medium text-foreground">{{ flashSales.from }}–{{ flashSales.to }}</span> of
                        <span class="font-medium text-foreground">{{ flashSales.total }}</span> flash sales
                    </p>
                    <nav v-if="flashSales.last_page > 1" class="flex flex-wrap gap-1" aria-label="Pagination">
                        <template v-for="(link, i) in flashSales.links" :key="i">
                            <Link
                                v-if="link.url && /^\d+$/.test(link.label)"
                                :href="link.url"
                                preserve-scroll
                                class="min-w-8 rounded border px-2.5 py-1 text-center text-xs"
                                :class="link.active ? 'border-[var(--brand-primary,#ee4d2d)] bg-[var(--brand-primary,#ee4d2d)] text-white' : 'hover:bg-muted'"
                            >
                                {{ link.label }}
                            </Link>
                        </template>
                    </nav>
                </div>
            </div>
        </section>
    </div>
</template>
