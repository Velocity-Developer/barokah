<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';

type Tracking = {
    courier: string | null;
    waybill_number: string | null;
    tracking_status: string | null;
    received_at: string | null;
    packed_at: string | null;
    picked_up_at: string | null;
    delivered_at: string | null;
    tracking_url: string | null;
    delivery_photo_url: string | null;
};

type Order = {
    order_number: string;
    status: string | null;
    tracking_status: string | null;
    courier: string | null;
    waybill_number: string | null;
    tracking_url: string | null;
    seller_trackings: Tracking[];
};

defineProps<{
    orderNumber: string;
    error: string | null;
    order: Order | null;
}>();

function statusLabel(status: string | null): string {
    return ({
        pending: 'Awaiting payment',
        paid: 'Payment received',
        packed: 'Being packed',
        shipped: 'Being shipped',
        completed: 'Order completed',
        cancelled: 'Order cancelled',
    } as Record<string, string>)[status ?? ''] ?? status ?? 'Not available';
}

function timeline(tracking: Tracking | undefined): { label: string; time: string }[] {
    if (!tracking) return [];

    return [
        { label: 'Order received', time: tracking.received_at },
        { label: 'Order packed', time: tracking.packed_at },
        { label: 'Order picked up by courier', time: tracking.picked_up_at },
        { label: 'Order delivered', time: tracking.delivered_at },
    ].filter((step): step is { label: string; time: string } => Boolean(step.time));
}
</script>

<template>
    <Head title="Track Order" />
    <MarketplaceLayout>
        <main class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-[var(--border-soft)] bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-2xl font-bold text-[var(--brand-primary)]">Track Your Order</h1>
                <p class="mt-2 text-sm leading-relaxed text-[var(--text-secondary)]">
                    Enter your order number to view the latest delivery status.
                    You can find it on the order confirmation page after checkout.
                </p>

                <form method="get" action="/tracking" class="mt-6 space-y-3">
                    <label for="order_number" class="block text-sm font-medium">Order number</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input id="order_number" name="order_number" :value="orderNumber" required autocomplete="off" placeholder="Example: ORD-20260917-ABC123" class="min-h-11 flex-1 rounded-md border border-[var(--border-default)] px-3 text-sm outline-none focus:border-[var(--brand-primary)]" />
                        <button type="submit" class="min-h-11 rounded-md bg-[var(--brand-primary)] px-5 text-sm font-semibold text-white hover:opacity-90">Track order</button>
                    </div>
                </form>

                <p v-if="error" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">Order number not found. Please check and try again.</p>

                <div v-if="order" class="mt-6 space-y-4 border-t border-[var(--border-soft)] pt-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs text-[var(--text-muted)]">Order number</p>
                            <p class="font-semibold">{{ order.order_number }}</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">{{ statusLabel(order.tracking_status || order.status) }}</span>
                    </div>

                    <div class="rounded-lg border border-[var(--border-soft)] p-4">
                        <p class="font-semibold">Order progress</p>
                        <ol v-if="timeline(order.seller_trackings[0]).length" class="mt-4 space-y-4 border-l-2 border-[var(--border-soft)] pl-5 text-sm">
                            <li v-for="step in timeline(order.seller_trackings[0])" :key="step.label + step.time" class="relative">
                                <span class="absolute -left-[25px] top-0.5 h-3 w-3 rounded-full border-2 border-white bg-[var(--brand-primary)] ring-1 ring-[var(--brand-primary)]" />
                                <p class="font-medium">{{ step.label }}</p>
                                <p class="mt-1 text-xs text-[var(--text-muted)]">{{ step.time }}</p>
                                <div v-if="step.label === 'Order picked up by courier' && order.seller_trackings[0]" class="mt-2 text-xs text-[var(--text-secondary)]">
                                    <p v-if="order.seller_trackings[0].courier">Courier: {{ order.seller_trackings[0].courier }}</p>
                                    <p v-if="order.seller_trackings[0].waybill_number">Tracking number: {{ order.seller_trackings[0].waybill_number }}</p>
                                    <a v-if="order.seller_trackings[0].tracking_url" :href="order.seller_trackings[0].tracking_url" target="_blank" rel="noopener" class="mt-1 inline-flex font-semibold text-[var(--brand-primary)] hover:underline">Open courier tracking</a>
                                </div>
                                <div v-if="step.label === 'Order delivered' && order.seller_trackings[0]?.delivery_photo_url" class="mt-3">
                                    <img :src="order.seller_trackings[0].delivery_photo_url" alt="Delivery proof" class="mt-2 max-h-72 rounded-lg border object-contain" />
                                </div>
                            </li>
                        </ol>
                    </div>

                </div>
            </section>

            <Link href="/" class="mt-5 inline-flex text-sm font-semibold text-[var(--brand-primary)] hover:underline">Back to marketplace</Link>
        </main>
    </MarketplaceLayout>
</template>
