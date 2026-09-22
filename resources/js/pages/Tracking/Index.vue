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
    items: { id: number; product_name: string; product_slug: string; reviewed: boolean }[];
};

type Order = {
    order_number: string;
    can_review: boolean;
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
        pending_payment: 'Awaiting payment',
        pending: 'Awaiting payment',
        paid: 'Payment received',
        processing: 'Being processed',
        packed: 'Being packed',
        shipped: 'Being shipped',
        completed: 'Order completed',
        cancelled: 'Order cancelled',
        expired: 'Order expired',
    } as Record<string, string>)[status ?? ''] ?? status ?? 'Not available';
}

function orderTimeline(
    orderStatus: string | null,
    tracking: Tracking | undefined,
): { label: string; time: string; description?: string }[] {
    const steps: { label: string; time: string; description?: string }[] = [];

    if (orderStatus === 'expired') {
        steps.push({ label: 'Pemesanan telah expired', time: '' });
        return steps;
    }

    if (orderStatus === 'paid') {
        steps.push({
            label: 'Payment Successful',
            time: '',
            description: 'Your order will be prepared shortly.',
        });
    }

    if (tracking) {
        if (tracking.received_at) {
            steps.push({ label: 'Order received', time: tracking.received_at });
        }
        if (tracking.packed_at) {
            steps.push({ label: 'Order packed', time: tracking.packed_at });
        }
        if (tracking.picked_up_at) {
            steps.push({ label: 'Order picked up by courier', time: tracking.picked_up_at });
        }
        if (tracking.delivered_at) {
            steps.push({ label: 'Order delivered', time: tracking.delivered_at });
        }
    }

    return steps;
}
</script>

<template>
    <Head title="Track Order" />
    <MarketplaceLayout>
        <div class="w-full py-8">
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
                    <div
                        v-if="order.status === 'expired'"
                        class="rounded-xl border border-red-200 bg-red-50 p-4"
                    >
                        <p class="text-sm font-semibold text-red-700">Pemesanan telah expired</p>
                        <p class="mt-1 text-sm text-red-600">
                            The payment window has expired. Please place a new order to continue shopping.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs text-[var(--text-muted)]">Order number</p>
                            <p class="font-semibold">{{ order.order_number }}</p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="
                                order.status === 'expired'
                                    ? 'bg-red-100 text-red-800'
                                    : order.status === 'paid' || order.status === 'completed'
                                      ? 'bg-emerald-100 text-emerald-800'
                                      : 'bg-amber-100 text-amber-800'
                            "
                        >
                            {{ statusLabel(order.tracking_status || order.status) }}
                        </span>
                    </div>

                    <template v-if="order.status !== 'expired'">
                        <div
                            v-for="tracking in order.seller_trackings"
                            :key="tracking.waybill_number ?? tracking.delivered_at ?? tracking.received_at"
                            class="rounded-lg border border-[var(--border-soft)] p-4"
                        >
                            <p class="font-semibold">Order progress</p>
                            <ol
                                v-if="orderTimeline(order.status, tracking).length"
                                class="mt-4 space-y-4 border-l-2 border-[var(--border-soft)] pl-5 text-sm"
                            >
                                <li
                                    v-for="step in orderTimeline(order.status, tracking)"
                                    :key="step.label + step.time"
                                    class="relative"
                                >
                                    <span
                                        class="absolute -left-[25px] top-0.5 h-3 w-3 rounded-full border-2 border-white bg-[var(--brand-primary)] ring-1 ring-[var(--brand-primary)]"
                                    />
                                    <p class="font-medium">{{ step.label }}</p>
                                    <p v-if="step.time" class="mt-1 text-xs text-[var(--text-muted)]">{{ step.time }}</p>
                                    <p v-if="step.description" class="mt-1 text-xs text-[var(--text-secondary)]">
                                        {{ step.description }}
                                    </p>
                                    <div v-if="step.label === 'Order picked up by courier'" class="mt-2 text-xs text-[var(--text-secondary)]">
                                        <p v-if="tracking.courier">Courier: {{ tracking.courier }}</p>
                                        <p v-if="tracking.waybill_number">Tracking number: {{ tracking.waybill_number }}</p>
                                        <a v-if="tracking.tracking_url" :href="tracking.tracking_url" target="_blank" rel="noopener" class="mt-1 inline-flex font-semibold text-[var(--brand-primary)] hover:underline">Open courier tracking</a>
                                    </div>
                                    <div v-if="step.label === 'Order delivered' && tracking.delivery_photo_url" class="mt-3">
                                        <img :src="tracking.delivery_photo_url" alt="Delivery proof" class="max-h-72 rounded-lg border object-contain" />
                                    </div>
                                </li>
                            </ol>
                        </div>
                    </template>

                    <div
                        v-if="order.can_review && order.seller_trackings.some((tracking) => tracking.delivered_at && tracking.items.length)"
                        class="rounded-xl border border-[var(--border-soft)] bg-[var(--bg-muted)] p-4"
                    >
                        <p class="text-base font-semibold">Rate your products</p>
                        <p class="mt-1 text-sm text-[var(--text-secondary)]">Your delivered products are ready for review.</p>
                        <div
                            v-for="tracking in order.seller_trackings.filter((item) => item.delivered_at && item.items.length)"
                            :key="tracking.waybill_number ?? tracking.delivered_at"
                            class="mt-3 space-y-2"
                        >
                            <div v-for="item in tracking.items" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-[var(--border-soft)] bg-white p-3 text-sm">
                                <span>{{ item.product_name }}</span>
                                <Link v-if="!item.reviewed" :href="`/rating/order-items/${item.id}`" class="rounded-md bg-[var(--brand-primary)] px-3 py-2 text-xs font-semibold text-white">Rate product</Link>
                                <span v-else class="text-right text-sm text-green-700">
                                    <span class="block font-medium">Rated: {{ item.rating }}/5</span>
                                    <span v-if="item.review" class="mt-1 block max-w-sm text-xs text-[var(--text-secondary)]">{{ item.review }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <Link href="/" class="mt-5 inline-flex text-sm font-semibold text-[var(--brand-primary)] hover:underline">Back to marketplace</Link>
        </div>
    </MarketplaceLayout>
</template>
