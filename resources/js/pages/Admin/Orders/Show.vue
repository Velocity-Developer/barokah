<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/admin/orders';
import { formatPrice } from '@/services/priceFormatter';

type AdminOrderDetailItem = {
    id: number;
    product_name: string;
    product_slug: string;
    price: string | number;
    quantity: number;
    subtotal: string | number;
    seller?: string | null;
    seller_id: number;
};

type AdminOrderDetailPayment = {
    id: number;
    payment_method: string;
    payment_gateway: string;
    status: string;
    amount: string | number;
    transaction_id?: string | null;
    proof_url?: string | null;
    proof_uploaded_at?: string | null;
    paid_at?: string | null;
} | null;

type AdminSellerTracking = {
    seller_id: number;
    seller_name?: string | null;
    courier?: string | null;
    waybill_number?: string | null;
    tracking_url?: string | null;
    tracking_status?: string | null;
    received_at?: string | null;
    packed_at?: string | null;
    picked_up_at?: string | null;
    delivered_at?: string | null;
    delivery_photo_url?: string | null;
};

type AdminOrderDetail = {
    id: number;
    order_number: string;
    status: string;
    currency_code: string;
    customer_name: string;
    customer_address: string;
    customer_state: string;
    customer_city?: string | null;
    customer_post_code: string;
    customer_phone: string;
    customer_email?: string | null;
    shipping_address?: string | null;
    shipping_state?: string | null;
    shipping_city?: string | null;
    shipping_post_code?: string | null;
    subtotal: string | number;
    shipping_fee: string | number;
    total: string | number;
    shipping_method: string;
    shipping_provider?: string | null;
    expired_at?: string | null;
    created_at?: string | null;
    notes?: string | null;
    payment: AdminOrderDetailPayment;
    items: AdminOrderDetailItem[];
    seller_trackings?: AdminSellerTracking[];
};

const props = defineProps<{
    order: AdminOrderDetail;
}>();

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

function displayMoney(value: string | number): string {
    const amount = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(amount) ? formatPrice(amount) : String(value);
}

function displayText(value: string | null | undefined): string {
    return value === null || value === undefined || value === ''
        ? '-'
        : value;
}

type TimelineStep = {
    label: string;
    time: string;
    description?: string;
    courierInfo?: { courier?: string | null; waybill_number?: string | null; tracking_url?: string | null };
    deliveryPhotoUrl?: string | null;
};

function orderTimeline(
    orderStatus: string,
    tracking: AdminSellerTracking,
): TimelineStep[] {
    const steps: TimelineStep[] = [];

    if (orderStatus === 'paid') {
        steps.push({
            label: 'Payment Successful',
            time: '',
            description: 'Your order will be prepared shortly.',
        });
    }

    if (tracking.received_at) {
        steps.push({ label: 'Order received', time: tracking.received_at });
    }
    if (tracking.packed_at) {
        steps.push({ label: 'Order packed', time: tracking.packed_at });
    }
    if (tracking.picked_up_at) {
        steps.push({
            label: 'Order picked up by courier',
            time: tracking.picked_up_at,
            courierInfo: {
                courier: tracking.courier,
                waybill_number: tracking.waybill_number,
                tracking_url: tracking.tracking_url,
            },
        });
    }
    if (tracking.delivered_at) {
        steps.push({
            label: 'Order delivered',
            time: tracking.delivered_at,
            deliveryPhotoUrl: tracking.delivery_photo_url,
        });
    }

    return steps;
}

function statusBadgeClass(status: string | null | undefined): string {
    switch (status) {
        case 'delivered':
            return 'bg-emerald-100 text-emerald-800';
        case 'shipped':
        case 'in_transit':
            return 'bg-sky-100 text-sky-800';
        case 'packed':
            return 'bg-indigo-100 text-indigo-800';
        case 'received':
            return 'bg-amber-100 text-amber-800';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

function statusLabel(status: string | null | undefined): string {
    return (
        {
            received: 'Received',
            packed: 'Packed',
            shipped: 'Shipped',
            in_transit: 'In transit',
            delivered: 'Delivered',
        } as Record<string, string>
    )[status ?? ''] ?? displayText(status);
}

const customerLocation = computed(() => {
    const parts = [
        props.order.customer_city,
        props.order.customer_state,
        props.order.customer_post_code,
    ].filter(
        (part): part is string => typeof part === 'string' && part !== '',
    );

    return parts.length > 0 ? parts.join(', ') : '-';
});

const shippingLocation = computed(() => {
    const parts = [
        props.order.shipping_city,
        props.order.shipping_state,
        props.order.shipping_post_code,
    ].filter(
        (part): part is string => typeof part === 'string' && part !== '',
    );

    return parts.length > 0 ? parts.join(', ') : '-';
});

const isManualPayment = computed(
    () =>
        props.order.payment?.payment_method === 'bank_transfer' ||
        props.order.payment?.payment_method === 'qr_code' ||
        props.order.payment?.payment_gateway === 'manual',
);
const isPaymentPending = computed(
    () => props.order.payment?.status === 'pending',
);
const isVerifying = ref(false);
const verifyError = ref<string | null>(null);

async function verify(status: 'paid' | 'failed'): Promise<void> {
    if (!props.order.payment) {
        return;
    }
    isVerifying.value = true;
    verifyError.value = null;
    try {
        const response = await fetch(
            `/api/v1/admin/payments/${props.order.payment.id}/verify`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement | null
                        )?.content ?? '',
                },
                body: JSON.stringify({ status }),
            },
        );
        if (!response.ok) {
            const payload = (await response
                .json()
                .catch(() => null)) as { message?: string } | null;
            verifyError.value = payload?.message ?? 'Verification failed.';
            return;
        }
        router.reload();
    } catch {
        verifyError.value = 'Verification failed. Please try again.';
    } finally {
        isVerifying.value = false;
    }
}
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Link
            :href="index()"
            class="text-muted-foreground w-fit text-sm hover:underline"
        >
            ← Back to Customer orders
        </Link>
        <Heading
            variant="small"
            :title="order.order_number"
            :description="`Placed ${displayText(order.created_at)}`"
        />

        <div class="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">{{ order.status }}</Badge>
            <Badge v-if="order.payment" variant="outline">
                {{ order.payment.payment_method }} ·
                {{ order.payment.status }}
            </Badge>
            <Badge v-else variant="outline">No payment yet</Badge>
            <Badge
                v-if="isManualPayment && isPaymentPending"
                variant="outline"
                class="border-amber-300 text-amber-700"
            >
                Manual · waiting verification
            </Badge>
        </div>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h3 class="mb-1 text-base font-medium">
                Items ({{ order.items.length }})
            </h3>
            <p class="text-muted-foreground mb-4 text-sm">
                Product snapshots saved at checkout time.
            </p>

            <p
                v-if="order.items.length === 0"
                class="text-muted-foreground text-sm"
            >
                No items in this order.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="text-muted-foreground border-b font-medium">
                            <th class="px-3 py-2 font-medium">Product</th>
                            <th class="px-3 py-2 font-medium">Store</th>
                            <th class="px-3 py-2 text-right font-medium">
                                Price
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Qty
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                Subtotal
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in order.items"
                            :key="item.id"
                            class="hover:bg-muted/50 border-b transition-colors last:border-0"
                        >
                            <td class="px-3 py-2 font-medium">
                                {{ item.product_name }}
                                <p class="text-muted-foreground text-xs font-normal">
                                    {{ item.product_slug }}
                                </p>
                            </td>
                            <td class="px-3 py-2">
                                {{ displayText(item.seller) }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                {{ displayMoney(item.price) }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                {{ item.quantity }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                {{ displayMoney(item.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t">
                            <td
                                colspan="4"
                                class="text-muted-foreground px-3 py-2 text-right"
                            >
                                Subtotal
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                            >
                                {{ displayMoney(order.subtotal) }}
                            </td>
                        </tr>
                        <tr>
                            <td
                                colspan="4"
                                class="text-muted-foreground px-3 py-2 text-right"
                            >
                                Shipping ({{ order.shipping_method }})
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                            >
                                {{ displayMoney(order.shipping_fee) }}
                            </td>
                        </tr>
                        <tr class="font-semibold">
                            <td colspan="4" class="px-3 py-2 text-right">
                                Total ({{ order.currency_code }})
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                            >
                                {{ displayMoney(order.total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <h3 class="mb-3 text-base font-medium">Billing customer</h3>
                <table class="w-full text-left text-sm">
                    <tbody>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Name
                            </th>
                            <td class="px-3 py-2">
                                {{ order.customer_name }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Phone
                            </th>
                            <td class="px-3 py-2">
                                {{ order.customer_phone }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Email
                            </th>
                            <td class="px-3 py-2">
                                {{ displayText(order.customer_email) }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Address
                            </th>
                            <td class="px-3 py-2">
                                {{ order.customer_address }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                City / State
                            </th>
                            <td class="px-3 py-2">
                                {{ customerLocation }}
                            </td>
                        </tr>
                        <tr v-if="order.notes" class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Notes
                            </th>
                            <td class="px-3 py-2">{{ order.notes }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <h3 class="mb-3 text-base font-medium">Delivery address</h3>
                <table class="w-full text-left text-sm">
                    <tbody>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Address
                            </th>
                            <td class="px-3 py-2">
                                {{
                                    displayText(order.shipping_address) ===
                                    '-'
                                        ? 'Same as billing'
                                        : order.shipping_address
                                }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                City / State
                            </th>
                            <td class="px-3 py-2">
                                {{ shippingLocation }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Shipping
                            </th>
                            <td class="px-3 py-2">
                                {{ order.shipping_method }}
                                <span
                                    v-if="order.shipping_provider"
                                    class="text-muted-foreground"
                                >
                                    · {{ order.shipping_provider }}
                                </span>
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                            >
                                Expires
                            </th>
                            <td class="px-3 py-2">
                                {{ displayText(order.expired_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h3 class="mb-3 text-base font-medium">Payment & verification</h3>
            <table class="w-full text-left text-sm">
                <tbody>
                    <tr class="border-b last:border-0">
                        <th
                            class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                        >
                            Method
                        </th>
                        <td class="px-3 py-2">
                            {{
                                order.payment
                                    ? order.payment.payment_method
                                    : '-'
                            }}
                        </td>
                    </tr>
                    <tr class="border-b last:border-0">
                        <th
                            class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                        >
                            Gateway
                        </th>
                        <td class="px-3 py-2">
                            {{
                                order.payment
                                    ? order.payment.payment_gateway
                                    : '-'
                            }}
                        </td>
                    </tr>
                    <tr class="border-b last:border-0">
                        <th
                            class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                        >
                            Pay status
                        </th>
                        <td class="px-3 py-2">
                            {{
                                order.payment
                                    ? order.payment.status
                                    : 'No payment yet'
                            }}
                        </td>
                    </tr>
                    <tr class="border-b last:border-0">
                        <th
                            class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                        >
                            Amount
                        </th>
                        <td class="px-3 py-2">
                            {{
                                order.payment
                                    ? displayMoney(order.payment.amount)
                                    : '-'
                            }}
                        </td>
                    </tr>
                    <tr class="border-b last:border-0">
                        <th
                            class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                        >
                            Transaction
                        </th>
                        <td class="px-3 py-2">
                            {{
                                displayText(
                                    order.payment?.transaction_id,
                                )
                            }}
                        </td>
                    </tr>
                    <tr class="border-b last:border-0">
                        <th
                            class="text-muted-foreground w-32 px-3 py-2 align-top font-medium"
                        >
                            Paid at
                        </th>
                        <td class="px-3 py-2">
                            {{
                                displayText(order.payment?.paid_at)
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="isManualPayment" class="mt-4 border-t pt-4">
                <h4 class="text-sm font-semibold">Payment receipt</h4>
                <div
                    v-if="order.payment?.proof_url"
                    class="mt-2 flex flex-wrap items-start gap-3"
                >
                    <a
                        :href="order.payment.proof_url"
                        target="_blank"
                        rel="noopener"
                    >
                        <img
                            :src="order.payment.proof_url"
                            alt="Payment receipt"
                            class="h-32 w-32 rounded border bg-white object-cover"
                        />
                    </a>
                    <div class="text-xs text-muted-foreground">
                        <p>
                            Uploaded
                            {{
                                displayText(
                                    order.payment.proof_uploaded_at,
                                )
                            }}
                        </p>
                        <a
                            :href="order.payment.proof_url"
                            target="_blank"
                            rel="noopener"
                            class="underline"
                        >
                            View full image
                        </a>
                    </div>
                </div>
                <p v-else class="mt-2 text-sm text-muted-foreground">
                    No receipt uploaded yet.
                </p>

                <div
                    v-if="isManualPayment && isPaymentPending"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <button
                        type="button"
                        :disabled="isVerifying"
                        class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        @click="verify('paid')"
                    >
                        {{ isVerifying ? 'Verifying…' : 'Mark paid' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isVerifying"
                        class="rounded border px-4 py-2 text-sm font-medium disabled:opacity-50"
                        @click="verify('failed')"
                    >
                        Mark failed
                    </button>
                </div>
                <p v-if="verifyError" class="mt-2 text-sm text-red-600">
                    {{ verifyError }}
                </p>
            </div>
        </div>

        <div
            v-if="order.payment?.status === 'paid'"
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h3 class="mb-4 text-base font-medium">Courier & tracking</h3>

            <div
                v-if="!order.seller_trackings?.length"
                class="text-muted-foreground text-sm"
            >
                No shipment tracking data for this order yet.
            </div>

            <div
                v-for="tracking in order.seller_trackings ?? []"
                :key="tracking.seller_id"
                class="mb-6 border-b pb-6 last:mb-0 last:border-0 last:pb-0"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h4 class="font-medium">
                        {{
                            tracking.seller_name ??
                            order.items.find((item) => item.seller_id === tracking.seller_id)
                                ?.seller ??
                            `Seller ${tracking.seller_id}`
                        }}
                    </h4>
                    <span
                        class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="statusBadgeClass(tracking.tracking_status)"
                    >
                        {{ statusLabel(tracking.tracking_status) }}
                    </span>
                </div>

                <div class="mb-5 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Courier
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ displayText(tracking.courier) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Waybill number
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ displayText(tracking.waybill_number) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            Courier link
                        </p>
                        <a
                            v-if="tracking.tracking_url"
                            :href="tracking.tracking_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline"
                        >
                            Open courier tracking →
                        </a>
                        <p v-else class="mt-1 text-sm">-</p>
                    </div>
                </div>

                <h5 class="text-sm font-semibold">Shipment progress</h5>
                <ol
                    v-if="orderTimeline(order.status, tracking).length"
                    class="mt-4 space-y-5 border-l-2 border-slate-200 pl-6 text-sm"
                >
                    <li
                        v-for="(step, idx) in orderTimeline(order.status, tracking)"
                        :key="step.label + step.time + idx"
                        class="relative"
                    >
                        <span
                            class="absolute -left-[29px] top-0.5 h-4 w-4 rounded-full border-2 border-white bg-indigo-500 ring-1 ring-indigo-500"
                        />
                        <p class="font-medium">{{ step.label }}</p>
                        <p
                            v-if="step.time"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ step.time }}
                        </p>
                        <p
                            v-if="step.description"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ step.description }}
                        </p>

                        <div
                            v-if="step.courierInfo && (step.courierInfo.courier || step.courierInfo.waybill_number || step.courierInfo.tracking_url)"
                            class="mt-3 rounded-md border bg-slate-50 p-3 text-xs text-slate-600"
                        >
                            <p v-if="step.courierInfo.courier" class="font-medium">
                                Courier: {{ step.courierInfo.courier }}
                            </p>
                            <p v-if="step.courierInfo.waybill_number" class="mt-1">
                                Tracking number:
                                <span class="font-mono font-medium">
                                    {{ step.courierInfo.waybill_number }}
                                </span>
                            </p>
                            <a
                                v-if="step.courierInfo.tracking_url"
                                :href="step.courierInfo.tracking_url"
                                target="_blank"
                                rel="noopener"
                                class="mt-1 inline-flex font-semibold text-indigo-600 hover:underline"
                            >
                                Open courier tracking
                            </a>
                        </div>

                        <div
                            v-if="step.deliveryPhotoUrl"
                            class="mt-3 inline-block overflow-hidden rounded-lg border"
                        >
                            <img
                                :src="step.deliveryPhotoUrl"
                                alt="Delivery proof"
                                class="max-h-80 max-w-full object-contain"
                            />
                        </div>
                    </li>
                </ol>
                <p
                    v-else
                    class="mt-4 text-sm text-muted-foreground"
                >
                    No shipment progress recorded yet.
                </p>
            </div>
        </div>
    </div>
</template>
