<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    CreditCard,
    ExternalLink,
    Package,
    Plus,
    ShoppingBag,
    Star,
    Truck,
    Wallet,
    Zap,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { dashboard as sellerDashboard, settings as storeSettings } from '@/routes/seller';
import { index as flashSalesIndex } from '@/routes/seller/flash-sales';
import { index as ordersIndex, show as orderShow } from '@/routes/seller/orders';
import { create as productCreate, edit as productEdit, index as productsIndex } from '@/routes/seller/products';

type SellerSummary = {
    id: number | null;
    store_name: string | null;
    slug: string | null;
    status: string | null;
    profile_photo_url: string | null;
    banner_url: string | null;
    public_url: string | null;
};

type Stats = {
    currency_code: string;
    revenue_formatted: string;
    revenue_this_month_formatted: string;
    orders_count: number;
    orders_today: number;
    items_sold: number;
    awaiting_payment: number;
    product_count: number;
    active_product_count: number;
    low_stock_count: number;
    followers_count: number;
    average_rating: number | null;
    ratings_count: number;
};

type Attention = {
    orders_to_ship: number;
    awaiting_payment: number;
    inactive_products: number;
    low_stock_products: { id: number; name: string; stock: number }[];
};

type ChartDay = { date: string; label: string; total: number; total_formatted: string; orders: number };

type RecentOrder = {
    order_number: string;
    customer_name: string;
    first_item: string | null;
    items_count: number;
    store_total_formatted: string;
    status: string;
    created_at: string | null;
};

type FlashSale = {
    id: number;
    product_name: string;
    price_formatted: string;
    quantity: number;
    quantity_sold: number;
    remaining_quantity: number;
    starts_at: string | null;
    ends_at: string | null;
    status: string;
};

const props = defineProps<{
    seller: SellerSummary;
    stats: Stats;
    attention: Attention;
    revenue_chart: ChartDay[];
    revenue_chart_total_formatted: string;
    recent_orders: RecentOrder[];
    flash_sales: FlashSale[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: sellerDashboard() }],
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
        label: 'Items sold',
        value: props.stats.items_sold.toLocaleString('en-GB'),
        hint: 'Paid orders only',
        icon: Package,
    },
    {
        label: 'Active products',
        value: props.stats.active_product_count.toLocaleString('en-GB'),
        hint: `of ${props.stats.product_count} in your catalogue`,
        icon: Package,
    },
]);

const tasks = computed(() =>
    [
        {
            count: props.attention.orders_to_ship,
            label: 'paid order to send',
            action: 'Open orders',
            href: ordersIndex({ query: { status: 'paid' } }),
            icon: Truck,
        },
        {
            count: props.attention.awaiting_payment,
            label: 'order waiting for payment',
            action: 'View',
            href: ordersIndex({ query: { status: 'pending_payment' } }),
            icon: CreditCard,
        },
        {
            count: props.attention.inactive_products,
            label: 'product not on sale',
            action: 'Review',
            href: productsIndex(),
            icon: Package,
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

const storeStatusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    suspended: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
};

function statusLabel(status: string | null): string {
    return (status ?? '').replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
}

function formatDay(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '—';
}

function soldPercent(sale: FlashSale): number {
    return sale.quantity > 0 ? Math.min(100, Math.round((sale.quantity_sold / sale.quantity) * 100)) : 0;
}
</script>

<template>
    <Head title="Seller dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <img v-if="seller.profile_photo_url" :src="seller.profile_photo_url" alt="" class="size-12 shrink-0 rounded-full border object-cover" />
                <span v-else class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[var(--accent-navy,#113366)] text-lg font-semibold text-white" aria-hidden="true">
                    {{ (seller.store_name ?? '?').charAt(0).toUpperCase() }}
                </span>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Heading variant="small" :title="seller.store_name ?? 'Your store'" />
                        <span
                            v-if="seller.status"
                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="storeStatusStyles[seller.status] ?? 'bg-muted text-muted-foreground ring-border'"
                        >
                            {{ statusLabel(seller.status) }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ stats.followers_count }} {{ stats.followers_count === 1 ? 'follower' : 'followers' }}
                        <template v-if="stats.ratings_count">
                            · <Star class="inline size-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />
                            {{ stats.average_rating }} ({{ stats.ratings_count }})
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a v-if="seller.public_url" :href="seller.public_url" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                    <ExternalLink class="size-4" aria-hidden="true" /> View store
                </a>
                <Link :href="storeSettings()" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Store settings</Link>
                <Link :href="productCreate()" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                    <Plus class="size-4" aria-hidden="true" /> Add product
                </Link>
            </div>
        </div>

        <p v-if="seller.status === 'suspended'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            This store is suspended, so its page and products are hidden from shoppers. Contact the marketplace admin to reopen it.
        </p>

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
                        <h3 class="text-base font-medium">Your revenue, last 14 days</h3>
                        <p class="text-xs text-muted-foreground">Your items in paid, processing, shipped and completed orders</p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Total <span class="font-semibold text-foreground">{{ revenue_chart_total_formatted }}</span>
                    </p>
                </div>

                <div class="relative mt-4">
                    <div
                        class="flex h-48 items-end gap-[2px] border-b border-border"
                        role="img"
                        :aria-label="`Daily revenue for the last ${revenue_chart.length} days`"
                        @mouseleave="hovered = null"
                    >
                        <div
                            v-for="(day, index) in revenue_chart"
                            :key="day.date"
                            class="group relative flex h-full flex-1 cursor-default items-end justify-center"
                            tabindex="0"
                            @mouseenter="hovered = index"
                            @focus="hovered = index"
                            @blur="hovered = null"
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
                    <caption>Daily revenue</caption>
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
                        <span class="text-xs font-normal text-muted-foreground">({{ stats.low_stock_count }} at 5 or fewer)</span>
                    </p>
                    <ul class="mt-2 space-y-1.5">
                        <li v-for="product in attention.low_stock_products" :key="product.id" class="flex items-center justify-between gap-3 text-sm">
                            <Link :href="productEdit(product.id)" class="min-w-0 truncate hover:underline">{{ product.name }}</Link>
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
                <div>
                    <h3 class="text-base font-medium">Recent orders</h3>
                    <p class="text-xs text-muted-foreground">Totals count your items only; other stores' items in the same checkout stay hidden.</p>
                </div>
                <Link :href="ordersIndex()" class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">
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
                            <th class="px-4 py-2 font-medium">Your items</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 text-right font-medium">Your total</th>
                            <th class="px-4 py-2 text-right font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="order in recent_orders" :key="order.order_number" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-2.5">
                                <Link :href="orderShow(order.order_number)" class="font-medium hover:underline">{{ order.order_number }}</Link>
                            </td>
                            <td class="px-4 py-2.5">{{ order.customer_name }}</td>
                            <td class="max-w-56 px-4 py-2.5">
                                <span class="block truncate">{{ order.first_item ?? '—' }}</span>
                                <span v-if="order.items_count > 1" class="text-xs text-muted-foreground">+{{ order.items_count - 1 }} more</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[order.status] ?? 'bg-muted text-muted-foreground ring-border'">
                                    {{ statusLabel(order.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ order.store_total_formatted }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap text-muted-foreground">{{ formatDate(order.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Flash sales -->
        <section class="rounded-xl border bg-card p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="flex items-center gap-1.5 text-base font-medium">
                    <Zap class="size-4 text-[var(--brand-primary,#ee4d2d)]" aria-hidden="true" /> Running flash sales
                </h3>
                <Link :href="flashSalesIndex()" class="inline-flex items-center gap-1 text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">
                    Manage <ArrowRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>

            <p v-if="flash_sales.length === 0" class="mt-2 text-sm text-muted-foreground">
                No flash sale is running or scheduled.
            </p>

            <ul v-else class="mt-3 grid gap-3 sm:grid-cols-2">
                <li v-for="sale in flash_sales" :key="sale.id" class="rounded-lg border p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ sale.product_name }}</p>
                            <p class="text-sm text-muted-foreground">{{ sale.price_formatted }} · {{ sale.quantity_sold }} of {{ sale.quantity }} sold</p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="sale.status === 'active'
                                ? 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900'
                                : 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900'"
                        >
                            {{ sale.status === 'active' ? 'Running' : 'Scheduled' }}
                        </span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full" :style="{ width: `${soldPercent(sale)}%`, backgroundColor: 'var(--brand-primary, #ee4d2d)' }" />
                    </div>
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        {{ formatDay(sale.starts_at) }} – {{ formatDay(sale.ends_at) }} · {{ sale.remaining_quantity }} left
                    </p>
                </li>
            </ul>
        </section>

    </div>
</template>
