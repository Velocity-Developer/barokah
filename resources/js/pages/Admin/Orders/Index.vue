<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { index, show } from '@/routes/admin/orders';
import { formatPrice } from '@/services/priceFormatter';
import { fetchAdminPaginated, type PaginatedLinks, type PaginatedMeta } from '../useAdminList';

type AdminOrderListItem = {
    id: number;
    order_number: string;
    status: string;
    total: string | number;
    customer_name?: string | null;
    created_at: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Customer orders',
                href: index(),
            },
        ],
    },
});

const orders = ref<AdminOrderListItem[]>([]);
const pageLinks = ref<PaginatedLinks>([]);
const pageMeta = ref<PaginatedMeta>({ current_page: 1, last_page: 1, total: 0, per_page: 15, from: 0, to: 0 });
const currentPage = ref(1);
const isLoading = ref(true);
const error = ref<string | null>(null);

function displayTotal(total: string | number): string {
    const amount = typeof total === 'number' ? total : Number(total);

    return Number.isFinite(amount) ? formatPrice(amount) : String(total);
}

function isNumericLabel(label: string): boolean {
    return /^\d+$/.test(label);
}

async function loadOrders(page: number = 1): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
        const result = await fetchAdminPaginated<AdminOrderListItem>('/api/v1/admin/orders', page);
        orders.value = result.data;
        pageLinks.value = result.links;
        pageMeta.value = result.meta;
        currentPage.value = result.meta.current_page;
    } catch {
        error.value = 'Customer orders are temporarily unavailable.';
    } finally {
        isLoading.value = false;
    }
}

function gotoPage(page: number): void {
    if (page < 1 || page > pageMeta.value.last_page) {
        return;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
    void loadOrders(page);
}

onMounted(() => loadOrders(1));
</script>

<template>
    <Head title="Customer orders" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            variant="small"
            title="Customer orders"
            description="All customer orders with status filter."
        />

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <div v-if="isLoading" class="animate-pulse space-y-2">
                <div class="bg-muted h-10 rounded" />
                <div class="bg-muted h-10 rounded" />
                <div class="bg-muted h-10 rounded" />
            </div>

            <p v-else-if="error" class="text-sm text-amber-600">{{ error }}</p>

            <p
                v-else-if="orders.length === 0"
                class="text-muted-foreground text-sm"
            >
                No customer orders yet.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="text-muted-foreground border-b font-medium">
                            <th class="px-3 py-2 font-medium">Order</th>
                            <th class="px-3 py-2 font-medium">Customer</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Total
                            </th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="order in orders"
                            :key="order.id"
                            class="hover:bg-muted/50 border-b transition-colors last:border-0"
                        >
                            <td class="px-3 py-2">
                                <Link
                                    :href="show(order.order_number)"
                                    class="font-medium hover:underline"
                                >
                                    {{ order.order_number }}
                                </Link>
                                <p class="text-muted-foreground text-xs">
                                    {{ order.created_at }}
                                </p>
                            </td>
                            <td class="px-3 py-2">
                                {{ order.customer_name ?? '-' }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                {{ displayTotal(order.total) }}
                            </td>
                            <td class="px-3 py-2">
                                <Badge variant="secondary">
                                    {{ order.status }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                <div
                                    class="flex items-center justify-end gap-3"
                                >
                                    <Link
                                        :href="show(order.order_number)"
                                        class="text-muted-foreground text-sm hover:underline"
                                    >
                                        Detail
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="!isLoading && !error && pageMeta.last_page > 1"
                class="mt-5 flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground text-xs">
                    Showing
                    <span class="font-medium text-slate-800">{{ pageMeta.from }}</span>
                    to
                    <span class="font-medium text-slate-800">{{ pageMeta.to }}</span>
                    of
                    <span class="font-medium text-slate-800">{{ pageMeta.total }}</span>
                    orders
                    <span class="ml-1">(page {{ pageMeta.current_page }} of {{ pageMeta.last_page }})</span>
                </p>

                <nav class="flex flex-wrap items-center gap-1">
                    <button
                        type="button"
                        :disabled="pageMeta.current_page === 1"
                        class="rounded border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="gotoPage(pageMeta.current_page - 1)"
                    >
                        &laquo; Previous
                    </button>

                    <template v-for="(link, idx) in pageLinks" :key="`${link.label}-${idx}`">
                        <button
                            v-if="link.url !== null && isNumericLabel(link.label)"
                            type="button"
                            :class="
                                link.active
                                    ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white'
                                    : 'border text-slate-700 hover:bg-slate-100'
                            "
                            class="rounded border px-3 py-1.5 text-xs font-medium"
                            @click="gotoPage(Number(link.label))"
                        >
                            {{ link.label }}
                        </button>
                        <span
                            v-else-if="!isNumericLabel(link.label)"
                            class="px-2 text-xs text-slate-400"
                        >
                            {{ link.label }}
                        </span>
                    </template>

                    <button
                        type="button"
                        :disabled="pageMeta.current_page === pageMeta.last_page"
                        class="rounded border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="gotoPage(pageMeta.current_page + 1)"
                    >
                        Next &raquo;
                    </button>
                </nav>
            </div>
        </div>
    </div>
</template>
