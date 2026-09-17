<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { dashboard as sellerDashboard } from '@/routes/seller';
import { useSettingsStore } from '@/stores/settings';

type SellerDashboardSeller = {
    store_name: string;
    slug: string;
    description?: string | null;
    status: string;
};

type SellerDashboardStats = {
    orders_count: number;
    items_count: number;
    revenue: string | number;
};

type SellerFlashSale = {
    id: number;
    product_name: string;
    product_slug: string;
    price: string | number;
    quantity: number;
    quantity_sold: number;
    remaining_quantity: number;
    starts_at: string;
    ends_at: string;
    status: string;
};

type SellerOrderItem = {
    id: number;
    product_name: string;
    price: string | number;
    quantity: number;
    subtotal: string | number;
};

type SellerOrder = {
    id: number;
    order_number: string;
    status: string;
    currency_code: string;
    total: string | number;
    created_at: string;
    items: SellerOrderItem[];
};

const props = defineProps<{
    seller: SellerDashboardSeller | null;
    stats: SellerDashboardStats;
    flashSales: SellerFlashSale[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Seller dashboard',
                href: sellerDashboard(),
            },
        ],
    },
});

const { formatAmount } = useSettingsStore();
const orders = ref<SellerOrder[]>([]);
const isLoadingOrders = ref(true);
const ordersError = ref<string | null>(null);

const statsCards = computed(() => [
    { label: 'Orders with your items', value: String(props.stats.orders_count) },
    { label: 'Items sold', value: String(props.stats.items_count) },
    {
        label: 'Items revenue',
        value: formatAmount(Number(props.stats.revenue)),
    },
]);

onMounted(async () => {
    try {
        const response = await fetch('/api/v1/seller/orders', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Failed to load seller orders.');
        }

        const payload = (await response.json()) as { data: SellerOrder[] };
        orders.value = payload.data;
    } catch {
        ordersError.value =
            'Seller orders are temporarily unavailable. Order data syncs from order items owned by this store.';
    } finally {
        isLoadingOrders.value = false;
    }
});
</script>

<template>
    <Head title="Seller dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            variant="small"
            title="Seller dashboard"
            :description="
                seller
                    ? `${seller.store_name} · ${seller.status}`
                    : 'Your store overview'
            "
        />

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                v-for="card in statsCards"
                :key="card.label"
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <p class="text-muted-foreground text-sm">{{ card.label }}</p>
                <p class="text-xl font-semibold tracking-tight">
                    {{ card.value }}
                </p>
            </div>
        </div>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h3 class="mb-1 text-base font-medium">Orders with your items</h3>
            <p class="text-muted-foreground mb-4 text-sm">
                Scoped to order items owned by this store. Other sellers'
                items in the same checkout stay hidden.
            </p>

            <div v-if="isLoadingOrders" class="animate-pulse space-y-2">
                <div class="bg-muted h-10 rounded" />
                <div class="bg-muted h-10 rounded" />
                <div class="bg-muted h-10 rounded" />
            </div>

            <p v-else-if="ordersError" class="text-sm text-amber-600">
                {{ ordersError }}
            </p>

            <p
                v-else-if="orders.length === 0"
                class="text-muted-foreground text-sm"
            >
                No orders yet. New orders containing your items will appear
                here.
            </p>

            <ul v-else class="divide-y">
                <li
                    v-for="order in orders"
                    :key="order.id"
                    class="py-3 first:pt-0 last:pb-0"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-medium">{{ order.order_number }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ order.status }}
                        </p>
                    </div>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center justify-between gap-2"
                        >
                            <span>
                                {{ item.product_name }} × {{ item.quantity }}
                            </span>
                            <span>{{ formatAmount(Number(item.subtotal)) }}</span>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <h3 class="mb-1 text-base font-medium">Flash sale toko</h3>
            <p v-if="flashSales.length === 0" class="text-muted-foreground text-sm">
                Belum ada flash sale di toko ini.
            </p>
            <ul v-else class="divide-y">
                <li v-for="sale in flashSales" :key="sale.id" class="py-3 first:pt-0 last:pb-0">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ sale.product_name }}</p>
                            <p class="text-muted-foreground text-sm">
                                {{ formatAmount(Number(sale.price)) }} · {{ sale.quantity_sold }} terjual dari {{ sale.quantity }} kuota
                            </p>
                        </div>
                        <span class="rounded-full bg-muted px-2 py-1 text-xs font-medium">{{ sale.status }}</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full bg-primary" :style="{ width: `${Math.min(100, (sale.quantity_sold / sale.quantity) * 100)}%` }" />
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{ new Date(sale.starts_at).toLocaleDateString() }} - {{ new Date(sale.ends_at).toLocaleDateString() }} · {{ sale.remaining_quantity }} tersisa
                    </p>
                </li>
            </ul>
        </div>
    </div>
</template>
