<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    CreditCard,
    Package,
    ShoppingBag,
    Truck,
    UserCheck,
    Wallet,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { dashboard } from '@/routes/admin';
import { index as ordersIndex, show as orderShow } from '@/routes/admin/orders';
import { index as paymentsIndex } from '@/routes/admin/payments';
import { edit as productEdit, index as productsIndex } from '@/routes/admin/products';
import { index as sellerApprovalsIndex } from '@/routes/admin/seller-approvals';

type Stats = {
    orders_count: number;
    orders_today: number;
    revenue: string | number;
    revenue_formatted: string;
    revenue_this_month_formatted: string;
    currency_code: string;
    pending_payments: number;
    pending_orders: number;
    low_stock_count: number;
    seller_count: number;
    active_seller_count: number;
    product_count: number;
    active_product_count: number;
};

type Attention = {
    seller_applications: number;
    manual_payments: number;
    orders_to_ship: number;
    low_stock_products: { id: number; name: string; stock: number; store: string | null }[];
};

type ChartDay = { date: string; label: string; total: number; total_formatted: string; orders: number };

type RecentOrder = {
    id: number;
    order_number: string;
    customer_name: string;
    first_item: string | null;
    items_count: number;
    total_formatted: string;
    status: string;
    created_at: string | null;
};

const props = defineProps<{
    stats: Stats;
    attention: Attention;
    revenue_chart: ChartDay[];
    revenue_chart_total_formatted: string;
    recent_orders: RecentOrder[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const kpis = computed<{ label: string; value: string; hint: string; icon: Component }[]>(() => [
    {
        label: `Revenue (${props.stats.currency_code})`,
        value: props.stats.revenue_formatted,
        hint: `${props.stats.revenue_this_month_formatted} this month`,
        icon: Wallet,
    },
    {
        label: 'Orders',
        value: props.stats.orders_count.toLocaleString('en-GB'),
        hint: `${props.stats.orders_today} today`,
        icon: ShoppingBag,
    },
    {
        label: 'Awaiting payment',
        value: props.stats.pending_orders.toLocaleString('en-GB'),
        hint: 'Orders not paid yet',
        icon: CreditCard,
    },
    {
        label: 'Active products',
        value: props.stats.active_product_count.toLocaleString('en-GB'),
        hint: `${props.stats.active_seller_count} active stores`,
        icon: Package,
    },
]);

const tasks = computed(() =>
    [
        {
            count: props.attention.seller_applications,
            label: 'seller application',
            action: 'Review',
            href: sellerApprovalsIndex(),
            icon: UserCheck,
        },
        {
            count: props.attention.manual_payments,
            label: 'manual payment to verify',
            action: 'Verify',
            href: paymentsIndex(),
            icon: CreditCard,
        },
        {
            count: props.attention.orders_to_ship,
            label: 'paid order to ship',
            action: 'Open orders',
            href: ordersIndex({ query: { status: 'paid' } }),
            icon: Truck,
        },
    ].filter((task) => task.count > 0),
);

const nothingToDo = computed(() => tasks.value.length === 0 && props.attention.low_stock_products.length === 0);

// Revenue chart: one series, so no legend; the heading names it.
const chartMax = computed(() => Math.max(...props.revenue_chart.map((day) => day.total), 0));
const hovered = ref<number | null>(null);

function barHeight(total: number): string {
    if (chartMax.value <= 0 || total <= 0) return '0%';

    // Headroom above the tallest bar keeps the tooltip clear of it.
    return `${Math.max(2, (total / chartMax.value) * 72)}%`;
}

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
    return status.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading variant="small" title="Dashboard" description="Marketplace overview: sales, orders and what needs your attention." />

        <!-- KPI row -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="kpi in kpis" :key="kpi.label" class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">{{ kpi.label }}</p>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground" aria-hidden="true">
                        <component :is="kpi.icon" class="size-4" />
                    </span>
                </div>
                <p class="mt-2 text-2xl font-semibold tracking-tight">{{ kpi.value }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ kpi.hint }}</p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <!-- Revenue chart -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <h3 class="text-base font-medium">Paid revenue, last 14 days</h3>
                        <p class="text-xs text-muted-foreground">Paid, processing, shipped and completed orders</p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Total
                        <span class="font-semibold text-foreground">
                            {{ revenue_chart_total_formatted }}
                        </span>
                    </p>
                </div>

                <div class="relative mt-4">
                    <div
                        class="flex h-48 items-end gap-[2px] border-b border-border"
                        role="img"
                        :aria-label="`Daily paid revenue for the last ${revenue_chart.length} days`"
                        @mouseleave="hovered = null"
                    >
                        <div
                            v-for="(day, index) in revenue_chart"
                            :key="day.date"
                            class="group relative flex h-full flex-1 cursor-default items-end justify-center"
                            @mouseenter="hovered = index"
                            @focus="hovered = index"
                            @blur="hovered = null"
                            tabindex="0"
                        >
                            <div
                                class="w-full max-w-9 rounded-t-[4px] transition-opacity"
                                :class="hovered !== null && hovered !== index ? 'opacity-50' : ''"
                                :style="{ height: barHeight(day.total), backgroundColor: 'var(--brand-primary, #ee4d2d)' }"
                            />
                            <div
                                v-if="hovered === index"
                                class="pointer-events-none absolute top-0 z-10 min-w-32 rounded-md border bg-popover px-2.5 py-1.5 text-xs text-popover-foreground shadow-md"
                                :class="index >= revenue_chart.length - 3 ? 'right-0' : index < 3 ? 'left-0' : 'left-1/2 -translate-x-1/2'"
                            >
                                <p class="font-medium">{{ day.label }}</p>
                                <p>{{ day.total_formatted }}</p>
                                <p class="text-muted-foreground">{{ day.orders }} {{ day.orders === 1 ? 'order' : 'orders' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-1.5 flex gap-[2px] text-[10px] text-muted-foreground">
                        <span v-for="(day, index) in revenue_chart" :key="day.date" class="flex-1 text-center">
                            {{ index % 2 === revenue_chart.length % 2 ? day.label : '' }}
                        </span>
                    </div>
                    <p v-if="chartMax === 0" class="absolute inset-x-0 top-16 text-center text-sm text-muted-foreground">
                        No paid orders in the last 14 days.
                    </p>
                </div>

                <table class="sr-only">
                    <caption>Daily paid revenue</caption>
                    <thead><tr><th>Date</th><th>Revenue</th><th>Orders</th></tr></thead>
                    <tbody>
                        <tr v-for="day in revenue_chart" :key="day.date"><td>{{ day.label }}</td><td>{{ day.total_formatted }}</td><td>{{ day.orders }}</td></tr>
                    </tbody>
                </table>
            </section>

            <!-- Needs attention -->
            <section class="rounded-xl border bg-card p-4 shadow-sm">
                <h3 class="text-base font-medium">Needs attention</h3>

                <div v-if="nothingToDo" class="mt-6 flex flex-col items-center gap-2 py-6 text-center text-sm text-muted-foreground">
                    <CheckCircle2 class="size-8 text-green-600" aria-hidden="true" />
                    All caught up — nothing waiting for you.
                </div>

                <ul v-else class="mt-3 divide-y">
                    <li v-for="task in tasks" :key="task.label" class="flex items-center gap-3 py-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300" aria-hidden="true">
                            <component :is="task.icon" class="size-4" />
                        </span>
                        <p class="flex-1 text-sm">
                            <span class="font-semibold">{{ task.count }}</span>
                            {{ task.label }}{{ task.count === 1 ? '' : 's' }}
                        </p>
                        <Link :href="task.href" class="inline-flex items-center gap-1 text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">
                            {{ task.action }} <ArrowRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </li>
                </ul>

                <div v-if="attention.low_stock_products.length" class="mt-2 border-t pt-3">
                    <p class="flex items-center gap-1.5 text-sm font-medium">
                        <AlertTriangle class="size-4 text-amber-600" aria-hidden="true" />
                        Low stock
                        <span class="text-xs font-normal text-muted-foreground">({{ stats.low_stock_count }} products at 5 or fewer)</span>
                    </p>
                    <ul class="mt-2 space-y-1.5">
                        <li v-for="product in attention.low_stock_products" :key="product.id" class="flex items-center justify-between gap-3 text-sm">
                            <Link :href="productEdit(product.id)" class="min-w-0 truncate hover:underline">
                                {{ product.name }}
                                <span v-if="product.store" class="text-xs text-muted-foreground">· {{ product.store }}</span>
                            </Link>
                            <span class="shrink-0 text-xs font-medium" :class="product.stock === 0 ? 'text-red-600' : 'text-amber-700 dark:text-amber-300'">
                                {{ product.stock === 0 ? 'Out of stock' : `${product.stock} left` }}
                            </span>
                        </li>
                    </ul>
                    <Link :href="productsIndex()" class="mt-2 inline-block text-xs font-medium text-muted-foreground hover:underline">View all products</Link>
                </div>
            </section>
        </div>

        <!-- Recent orders -->
        <section class="rounded-xl border bg-card shadow-sm">
            <div class="flex items-center justify-between gap-2 p-4 pb-2">
                <h3 class="text-base font-medium">Recent orders</h3>
                <Link :href="ordersIndex()" class="inline-flex items-center gap-1 text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">
                    View all <ArrowRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>
            <p v-if="recent_orders.length === 0" class="p-4 pt-0 text-sm text-muted-foreground">No orders yet.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs text-muted-foreground">
                            <th class="px-4 py-2 font-medium">Order</th>
                            <th class="px-4 py-2 font-medium">Customer</th>
                            <th class="px-4 py-2 font-medium">Items</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 text-right font-medium">Total</th>
                            <th class="px-4 py-2 text-right font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in recent_orders" :key="order.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-2.5">
                                <Link :href="orderShow(order.order_number)" class="font-medium hover:underline">{{ order.order_number }}</Link>
                            </td>
                            <td class="px-4 py-2.5">{{ order.customer_name }}</td>
                            <td class="max-w-56 px-4 py-2.5">
                                <span class="block truncate">{{ order.first_item ?? '—' }}</span>
                                <span v-if="order.items_count > 1" class="text-xs text-muted-foreground">+{{ order.items_count - 1 }} more</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="statusStyles[order.status] ?? 'bg-muted text-muted-foreground ring-border'"
                                >
                                    {{ statusLabel(order.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ order.total_formatted }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap text-muted-foreground">{{ formatDate(order.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
