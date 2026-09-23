<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/seller/orders';
import { formatPrice } from '@/services/priceFormatter';

type TimelineStep = {
    label: string;
    time: string;
    description?: string;
    courierInfo?: { courier?: string | null; waybill_number?: string | null; tracking_url?: string | null };
    deliveryPhotoUrl?: string | null;
};

type OrderItem = { id: number; product_name: string; price: string | number; quantity: number; subtotal: string | number };
type SellerTracking = {
    courier?: string | null;
    waybill_number?: string | null;
    tracking_url?: string | null;
    tracking_status?: string | null;
    received_at?: string | null;
    packed_at?: string | null;
    picked_up_at?: string | null;
    delivered_at?: string | null;
    delivery_photo_url?: string | null;
    delivery_photo_path?: string | null;
};
type SellerPayment = { status?: string | null; payment_method?: string | null };
type SellerOrder = {
    tracking?: SellerTracking | null;
    payment?: SellerPayment | null;
    subtotal: string | number; shipping_fee: string | number; total: string | number; currency_code: string; items: OrderItem[];
    status?: string | null; created_at?: string | null; customer_name: string; customer_address: string; customer_state: string;
    customer_city?: string | null; customer_post_code: string; shipping_address?: string | null; shipping_state?: string | null;
    shipping_city?: string | null; shipping_post_code?: string | null; shipping_method?: string | null;
};

const props = defineProps<{ orderNumber: string }>();
const order = ref<SellerOrder | null>(null);
const courier = ref('');
const waybillNumber = ref('');
const trackingUrl = ref('');
const trackingStatus = ref<
    'received' | 'packed' | 'shipped' | 'delivered'
>('packed');
const deliveryPhoto = ref<File | null>(null);
const message = ref('');
const errors = ref<Record<string, string>>({});
const isSaving = ref(false);

/** What the buyer sees for each step, in the order they happen. */
const TRACKING_STEPS = [
    { value: 'received', label: 'Order received', hint: 'You have the order and will start packing.' },
    { value: 'packed', label: 'Packed', hint: 'Parcel is ready for the courier.' },
    { value: 'shipped', label: 'Handed to courier', hint: 'On its way to the buyer.' },
    { value: 'delivered', label: 'Delivered', hint: 'Arrived. Add a photo as proof if you have one.' },
] as const;
const deliveryPhotoPreview = ref('');

const isPaid = computed(
    () =>
        order.value?.payment?.status === 'paid' ||
        order.value?.payment?.status === 'verified',
);
const isPendingPayment = computed(
    () => order.value?.status === 'pending_payment',
);
const paymentLabel = computed(() =>
    (
        {
            pending: 'Pending',
            paid: 'Paid',
            failed: 'Failed',
            expired: 'Expired',
            verified: 'Paid (verified)',
        } as Record<string, string>
    )[order.value?.payment?.status ?? ''] ??
        order.value?.payment?.status ??
        '-',
);
const orderStatusLabel = computed(() =>
    (
        {
            pending_payment: 'Awaiting payment',
            paid: 'Paid',
            processing: 'Processing',
            packed: 'Packed',
            shipped: 'Shipped',
            completed: 'Completed',
            cancelled: 'Cancelled',
            expired: 'Expired',
        } as Record<string, string>
    )[order.value?.status ?? ''] ?? order.value?.status ?? '-',
);
const orderStatusClass = computed(() => {
    switch (order.value?.status) {
        case 'pending_payment':
            return 'bg-amber-100 text-amber-800';
        case 'paid':
        case 'processing':
            return 'bg-indigo-100 text-indigo-800';
        case 'packed':
        case 'shipped':
        case 'completed':
            return 'bg-emerald-100 text-emerald-800';
        case 'cancelled':
        case 'expired':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-slate-100 text-slate-700';
    }
});

const shippingMethodLabel = computed(() =>
    ({ fixed: 'Fixed rate', external: 'Courier rate' } as Record<string, string>)[order.value?.shipping_method ?? ''] ??
        displayText(order.value?.shipping_method),
);

const customerLocation = computed(() =>
    order.value
        ? [
              order.value.customer_city,
              order.value.customer_state,
              order.value.customer_post_code,
          ]
              .filter(Boolean)
              .join(', ') || '-'
        : '-',
);
const shippingLocation = computed(() =>
    order.value
        ? [
              order.value.shipping_city,
              order.value.shipping_state,
              order.value.shipping_post_code,
          ]
              .filter(Boolean)
              .join(', ') || '-'
        : '-',
);

function displayMoney(value: string | number): string {
    const amount = typeof value === 'number' ? value : Number(value);
    return Number.isFinite(amount) ? formatPrice(amount) : String(value);
}
function onDeliveryPhotoChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    deliveryPhoto.value = file;

    if (file) {
        if (deliveryPhotoPreview.value?.startsWith('blob:')) {
            URL.revokeObjectURL(deliveryPhotoPreview.value);
        }

        deliveryPhotoPreview.value = URL.createObjectURL(file);
    }
}

function displayText(value: string | null | undefined): string {
    return value || '-';
}

/** Timestamps arrive as ISO strings; show them in the reader's local time. */
function formatDateTime(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

function trackingStatusLabel(status: string | null | undefined): string {
    return (
        {
            received: 'Received',
            packed: 'Packed',
            shipped: 'Shipped',
            in_transit: 'In transit',
            picked_up: 'Picked up',
            delivered: 'Delivered',
        } as Record<string, string>
    )[status ?? ''] ?? status ?? 'Not updated';
}

function trackingStatusClass(status: string | null | undefined): string {
    switch (status) {
        case 'delivered':
            return 'bg-emerald-100 text-emerald-800';
        case 'shipped':
        case 'in_transit':
        case 'picked_up':
            return 'bg-sky-100 text-sky-800';
        case 'packed':
            return 'bg-indigo-100 text-indigo-800';
        case 'received':
            return 'bg-amber-100 text-amber-800';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

const progressTimeline = computed<TimelineStep[]>(() => {
    const steps: TimelineStep[] = [];
    const tr = order.value?.tracking;
    if (!tr) {
        return steps;
    }
    if (order.value?.status === 'paid') {
        steps.push({
            label: 'Payment Successful',
            time: '',
            description: 'Your order will be prepared shortly.',
        });
    }
    if (tr.received_at) {
        steps.push({ label: 'Order received', time: tr.received_at });
    }
    if (tr.packed_at) {
        steps.push({ label: 'Order packed', time: tr.packed_at });
    }
    if (tr.picked_up_at) {
        steps.push({
            label: 'Order picked up by courier',
            time: tr.picked_up_at,
            courierInfo: {
                courier: tr.courier,
                waybill_number: tr.waybill_number,
                tracking_url: tr.tracking_url,
            },
        });
    }
    if (tr.delivered_at) {
        steps.push({
            label: 'Order delivered',
            time: tr.delivered_at,
            deliveryPhotoUrl:
                tr.delivery_photo_url ??
                (tr.delivery_photo_path
                    ? `/storage/${tr.delivery_photo_path}`
                    : null),
        });
    }
    return steps;
});

onMounted(async () => {
    const response = await fetch(`/api/v1/seller/orders/${props.orderNumber}`, {
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        message.value = 'Order unavailable.';
        return;
    }
    order.value = (await response.json() as { data: SellerOrder }).data;
    if (isPaid.value) {
        courier.value = order.value.tracking?.courier ?? '';
        waybillNumber.value = order.value.tracking?.waybill_number ?? '';
        trackingUrl.value = order.value.tracking?.tracking_url ?? '';
        // The API stores the courier handover as picked_up; the form calls it shipped.
        const current = order.value.tracking?.tracking_status === 'picked_up'
            ? 'shipped'
            : order.value.tracking?.tracking_status;

        if (
            current === 'received' ||
            current === 'packed' ||
            current === 'shipped' ||
            current === 'delivered'
        ) {
            trackingStatus.value = current;
        }
        const photo =
            order.value.tracking?.delivery_photo_url ??
            (order.value.tracking?.delivery_photo_path
                ? `/storage/${order.value.tracking.delivery_photo_path}`
                : '');
        deliveryPhotoPreview.value = photo;
    }
});

async function save(): Promise<void> {
    if (!isPaid.value) {
        toast.error('Tracking can only be updated once the order is paid.');

        return;
    }

    isSaving.value = true;
    message.value = '';
    errors.value = {};
    const form = new FormData();
    form.append('tracking_status', trackingStatus.value);
    if (courier.value) {
        form.append('courier', courier.value);
    }
    if (waybillNumber.value) {
        form.append('waybill_number', waybillNumber.value);
    }
    if (trackingUrl.value) {
        form.append('tracking_url', trackingUrl.value);
    }
    if (trackingStatus.value === 'delivered' && deliveryPhoto.value) {
        form.append('delivery_photo', deliveryPhoto.value);
    }

    const response = await fetch(
        `/api/v1/seller/orders/${props.orderNumber}/tracking`,
        {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    (document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement | null)?.content ?? '',
            },
            body: form,
        },
    );
    isSaving.value = false;

    if (response.ok) {
        toast.success('Tracking saved. The buyer can see it now.');
        deliveryPhoto.value = null;

        return;
    }

    const payload = (await response.json().catch(
        () => null,
    )) as { message?: string; errors?: Record<string, string[]> } | null;

    for (const [field, messages] of Object.entries(payload?.errors ?? {})) {
        errors.value[field] = messages[0] ?? 'Invalid value.';
    }

    toast.error(payload?.message ?? 'Tracking could not be saved.');
}

const trackingHint = computed(() => TRACKING_STEPS.find((step) => step.value === trackingStatus.value)?.hint ?? '');

/** The saved courier details, shown above the form so they are easy to check. */
const savedTracking = computed(() => {
    const tracking = order.value?.tracking;

    if (!tracking || (!tracking.courier && !tracking.waybill_number && !tracking.tracking_url)) {
        return null;
    }

    return tracking;
});
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customer orders', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Order ${orderNumber}`" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Link
            :href="index()"
            class="text-muted-foreground w-fit text-sm hover:underline"
        >
            ← Back to Customer orders
        </Link>
        <Heading
            variant="small"
            :title="orderNumber"
            :description="`Placed ${formatDateTime(order?.created_at)}`"
        />
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="orderStatusClass"
            >
                {{ orderStatusLabel }}
            </span>
            <Badge v-if="order?.payment" variant="outline">
                Payment: {{ paymentLabel }}
                <span v-if="order.payment.payment_method">
                    · {{ order.payment.payment_method }}
                </span>
            </Badge>
        </div>

        <div
            v-if="isPendingPayment"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm"
        >
            <p class="font-semibold text-amber-800">Awaiting customer payment</p>
            <p class="mt-1 text-amber-700">
                You can view the order details, but shipment and tracking
                editing is disabled until the customer completes payment.
            </p>
        </div>

        <div
            v-if="order"
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <h3 class="mb-1 text-base font-medium">
                Items ({{ order.items.length }})
            </h3>
            <p class="text-muted-foreground mb-4 text-sm">
                Products assigned to this seller.
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
                        <tr
                            class="text-muted-foreground border-b font-medium"
                        >
                            <th class="px-3 py-2 font-medium">Product</th>
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
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                            >
                                {{ displayMoney(item.price) }}
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                            >
                                {{ item.quantity }}
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                            >
                                {{ displayMoney(item.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t">
                            <td
                                colspan="3"
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
                                colspan="3"
                                class="text-muted-foreground px-3 py-2 text-right"
                            >
                                Shipping
                            </td>
                            <td
                                class="px-3 py-2 text-right whitespace-nowrap"
                            >
                                {{ displayMoney(order.shipping_fee) }}
                            </td>
                        </tr>
                        <tr class="font-semibold">
                            <td colspan="3" class="px-3 py-2 text-right">
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

        <div v-if="order" class="grid gap-4 lg:grid-cols-2">
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
            >
                <h3 class="mb-3 text-base font-medium">Billing customer</h3>
                <table class="w-full text-left text-sm">
                    <tbody>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 text-left align-top font-medium"
                            >
                                Name
                            </th>
                            <td class="px-3 py-2">
                                {{ order.customer_name }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 text-left align-top font-medium"
                            >
                                Address
                            </th>
                            <td class="px-3 py-2">
                                {{ order.customer_address }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 text-left align-top font-medium"
                            >
                                City / State
                            </th>
                            <td class="px-3 py-2">
                                {{ customerLocation }}
                            </td>
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
                                class="text-muted-foreground w-32 px-3 py-2 text-left align-top font-medium"
                            >
                                Address
                            </th>
                            <td class="px-3 py-2">
                                {{ order.shipping_address || 'Same as billing' }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 text-left align-top font-medium"
                            >
                                City / State
                            </th>
                            <td class="px-3 py-2">
                                {{ shippingLocation }}
                            </td>
                        </tr>
                        <tr class="border-b last:border-0">
                            <th
                                class="text-muted-foreground w-32 px-3 py-2 text-left align-top font-medium"
                            >
                                Shipping
                            </th>
                            <td class="px-3 py-2">
                                {{ shippingMethodLabel }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="order && !isPendingPayment"
            class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-medium">Courier &amp; tracking</h3>
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="trackingStatusClass(order.tracking?.tracking_status)"
                >
                    {{ trackingStatusLabel(order.tracking?.tracking_status) }}
                </span>
            </div>

            <div
                v-if="order.tracking && (order.tracking.courier || order.tracking.waybill_number || order.tracking.tracking_url)"
                class="mb-5 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div>
                    <p
                        class="text-muted-foreground text-xs font-medium uppercase tracking-wide"
                    >
                        Courier
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ displayText(order.tracking.courier) }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-muted-foreground text-xs font-medium uppercase tracking-wide"
                    >
                        Waybill number
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ displayText(order.tracking.waybill_number) }}
                    </p>
                </div>
                <div>
                    <p
                        class="text-muted-foreground text-xs font-medium uppercase tracking-wide"
                    >
                        Courier link
                    </p>
                    <a
                        v-if="order.tracking.tracking_url"
                        :href="order.tracking.tracking_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline"
                    >
                        Open courier tracking →
                    </a>
                    <p v-else class="mt-1 text-sm">-</p>
                </div>
            </div>

            <h4
                v-if="progressTimeline.length > 0 || isPaid"
                class="text-sm font-semibold"
            >
                Shipment progress
            </h4>
            <ol
                v-if="progressTimeline.length > 0"
                class="mt-4 space-y-5 border-l-2 border-slate-200 pl-6 text-sm"
            >
                <li
                    v-for="(step, idx) in progressTimeline"
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
                        {{ formatDateTime(step.time) }}
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
                        <p
                            v-if="step.courierInfo.courier"
                            class="font-medium"
                        >
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
                v-else-if="isPaid"
                class="mt-4 text-sm text-muted-foreground"
            >
                No shipment progress yet. Update tracking below to start the
                shipment flow.
            </p>

            <p v-if="!isPaid" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                Tracking opens once the buyer has paid.
            </p>

            <form v-else class="mt-6 grid gap-4 border-t pt-5" @submit.prevent="save">
                <div>
                    <h4 class="text-base font-medium">Update tracking</h4>
                    <p class="text-sm text-muted-foreground">The buyer sees this on their order page, so keep it current.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid content-start gap-2">
                        <Label for="tracking-status">Shipment status</Label>
                        <select id="tracking-status" v-model="trackingStatus" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                            <option v-for="step in TRACKING_STEPS" :key="step.value" :value="step.value">{{ step.label }}</option>
                        </select>
                        <p class="text-xs text-muted-foreground">{{ trackingHint }}</p>
                        <InputError :message="errors.tracking_status" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="tracking-courier">Courier</Label>
                        <Input id="tracking-courier" v-model="courier" type="text" maxlength="255" placeholder="J&amp;T, Pos Laju, DHL…" />
                        <InputError :message="errors.courier" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="tracking-waybill">Waybill number</Label>
                        <Input id="tracking-waybill" v-model="waybillNumber" type="text" maxlength="255" placeholder="e.g. 630123456789" class="font-mono" />
                        <p class="text-xs text-muted-foreground">The number printed on the parcel label.</p>
                        <InputError :message="errors.waybill_number" />
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="tracking-url">Tracking link</Label>
                        <Input id="tracking-url" v-model="trackingUrl" type="url" maxlength="500" placeholder="https://courier.com/track/630123456789" />
                        <p class="text-xs text-muted-foreground">Optional. The courier page where the buyer can follow the parcel.</p>
                        <InputError :message="errors.tracking_url" />
                    </div>

                    <div v-if="trackingStatus === 'delivered'" class="grid content-start gap-2 sm:col-span-2">
                        <Label for="delivery-photo">Proof of delivery</Label>
                        <input
                            id="delivery-photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="h-9 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-xs"
                            @change="onDeliveryPhotoChange"
                        />
                        <p class="text-xs text-muted-foreground">Optional photo of the delivered parcel. JPG, PNG or WebP, up to 5 MB.</p>
                        <InputError :message="errors.delivery_photo" />
                        <img
                            v-if="deliveryPhotoPreview"
                            :src="deliveryPhotoPreview"
                            alt="Proof of delivery"
                            class="max-h-48 w-fit rounded-lg border object-contain"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t pt-4">
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="inline-flex h-9 items-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    >
                        {{ isSaving ? 'Saving…' : 'Save tracking' }}
                    </button>
                    <p v-if="message" class="text-sm text-muted-foreground">{{ message }}</p>
                </div>
            </form>
        </div>
    </div>
</template>
