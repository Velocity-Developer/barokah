<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import { useSettingsStore } from '@/stores/settings';

type ConfirmationItem = {
    product_name: string;
    quantity: number;
    price: string | number;
    subtotal: string | number;
};

type ConfirmationTracking = {
    seller_id: number;
    seller_name?: string | null;
    courier?: string | null;
    waybill_number?: string | null;
    tracking_url?: string | null;
    tracking_status?: string | null;
};

type ConfirmationOrder = {
    order_number: string;
    status: string;
    is_expired: boolean;
    is_payable: boolean;
    payment_status: string | null;
    payment_method: string | null;
    payment_gateway: string | null;
    proof_url: string | null;
    proof_uploaded_at: string | null;
    currency_code: string;
    customer_name: string;
    customer_phone?: string | null;
    customer_email?: string | null;
    customer_address?: string | null;
    customer_state?: string | null;
    customer_city?: string | null;
    customer_post_code?: string | null;
    shipping_address?: string | null;
    shipping_state?: string | null;
    shipping_city?: string | null;
    shipping_post_code?: string | null;
    subtotal: string | number;
    shipping_fee: string | number;
    total: string | number;
    shipping_method: string;
    shipping_provider?: string | null;
    courier?: string | null;
    waybill_number?: string | null;
    tracking_url?: string | null;
    tracking_status?: string | null;
    expired_at: string | null;
    created_at: string | null;
    items: ConfirmationItem[];
    seller_trackings?: ConfirmationTracking[];
};

const props = defineProps<{ order: ConfirmationOrder }>();

const shippableTrackings = computed(
    () =>
        props.order.seller_trackings?.filter(
            (tracking) =>
                Boolean(tracking.courier) ||
                Boolean(tracking.waybill_number) ||
                Boolean(tracking.tracking_url),
        ) ?? [],
);

const { formatAmount, getSettingValue } = useSettingsStore();

const queryMethod =
    typeof window === 'undefined'
        ? null
        : new URLSearchParams(window.location.search).get('payment_method');

const paymentMethod = computed(
    () => queryMethod ?? props.order.payment_method,
);
const isManualPayment = computed(
    () =>
        paymentMethod.value === 'bank_transfer' ||
        paymentMethod.value === 'qr_code' ||
        props.order.payment_gateway === 'manual',
);
const isBankTransfer = computed(
    () => paymentMethod.value === 'bank_transfer',
);
const isQrManual = computed(() => paymentMethod.value === 'qr_code');

const bankName = computed(() =>
    getSettingValue<string>('payment.bank_name', ''),
);
const bankAccountName = computed(() =>
    getSettingValue<string>('payment.bank_account_name', ''),
);
const bankAccountNumber = computed(() =>
    getSettingValue<string>('payment.bank_account_number', ''),
);
const qrCodeUrl = computed(() =>
    getSettingValue<string>('payment.qr_code_url', ''),
);

const paymentStatus = ref<string | null>(props.order.payment_status);
const redirectUrl = ref<string | null>(null);
const qrPayload = ref<string | null>(null);
const proofUrl = ref<string | null>(props.order.proof_url ?? null);
const proofUploadedAt = ref<string | null>(
    props.order.proof_uploaded_at ?? null,
);

const selectedFile = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const isUploading = ref(false);
const uploadError = ref<string | null>(null);
const uploadSuccess = ref<string | null>(null);

const canUploadProof = computed(
    () =>
        isManualPayment.value &&
        paymentStatus.value === 'pending' &&
        props.order.is_payable,
);
const paymentLabel = computed(() => {
    switch (paymentMethod.value) {
        case 'bank_transfer':
            return 'Bank Transfer';
        case 'qr_code':
            return 'QR Code';
        case 'fpx':
            return 'FPX via PayNet';
        case 'duitnow':
            return 'DuitNow QR via PayNet';
        default:
            return paymentMethod.value ?? 'Manual';
    }
});

function fullAddress(
    address?: string | null,
    city?: string | null,
    state?: string | null,
    postCode?: string | null,
): string {
    const parts = [address, city, state, postCode].filter(
        (part): part is string => typeof part === 'string' && part !== '',
    );
    return parts.length > 0 ? parts.join(', ') : '-';
}

async function refreshPayment(): Promise<void> {
    try {
        const response = await fetch(
            `/api/v1/orders/${props.order.order_number}/payment`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) {
            return;
        }
        const payload = (await response.json()) as {
            data?: {
                status?: string;
                redirect_url?: string | null;
                qr_payload?: string | null;
                proof_url?: string | null;
                proof_uploaded_at?: string | null;
            };
        };
        const data = payload.data;
        if (!data) {
            return;
        }
        if (data.status) {
            paymentStatus.value = data.status;
        }
        redirectUrl.value = data.redirect_url ?? redirectUrl.value;
        qrPayload.value = data.qr_payload ?? qrPayload.value;
        if (data.proof_url !== undefined) {
            proofUrl.value = data.proof_url;
        }
        if (data.proof_uploaded_at !== undefined) {
            proofUploadedAt.value = data.proof_uploaded_at;
        }
        if (data.status && data.status !== 'pending' && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    } catch {
        // Keep showing the server-rendered snapshot; PayNet callbacks may arrive later.
    }
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

function onFileChange(event: Event): void {
    uploadError.value = null;
    uploadSuccess.value = null;
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
    selectedFile.value = null;
    if (!file) {
        return;
    }
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        uploadError.value = 'Use a JPG, PNG, or WebP image.';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        uploadError.value = 'Maximum file size is 5 MB.';
        return;
    }
    selectedFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
}

async function uploadProof(): Promise<void> {
    if (!selectedFile.value) {
        uploadError.value = 'Choose a receipt image first.';
        return;
    }
    isUploading.value = true;
    uploadError.value = null;
    uploadSuccess.value = null;
    try {
        const form = new FormData();
        form.append('proof', selectedFile.value);
        const response = await fetch(
            `/api/v1/orders/${props.order.order_number}/payment/proof`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: form,
            },
        );
        const payload = (await response.json().catch(() => null)) as {
            data?: {
                proof_url?: string | null;
                proof_uploaded_at?: string | null;
            };
            message?: string;
            errors?: Record<string, string[]>;
        } | null;
        if (!response.ok) {
            const firstError = payload?.errors
                ? Object.values(payload.errors)[0]?.[0]
                : null;
            uploadError.value =
                firstError ?? payload?.message ?? 'Upload failed. Please try again.';
            return;
        }
        proofUrl.value = payload?.data?.proof_url ?? proofUrl.value;
        proofUploadedAt.value =
            payload?.data?.proof_uploaded_at ?? proofUploadedAt.value;
        uploadSuccess.value =
            'Receipt uploaded. Waiting for admin verification.';
        selectedFile.value = null;
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
        }
        await refreshPayment();
    } catch {
        uploadError.value = 'Upload failed. Please try again.';
    } finally {
        isUploading.value = false;
    }
}

onMounted(() => {
    void refreshPayment();
    if (paymentStatus.value === 'pending' && !isManualPayment.value) {
        pollTimer = setInterval(() => void refreshPayment(), 3000);
    }
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});
</script>

<template>
    <MarketplaceLayout>
        <Head :title="`Order ${order.order_number}`" />

        <div class="w-full py-6">
            <nav class="mb-4 flex flex-wrap items-center gap-1.5 text-xs text-[var(--text-muted)]">
                <Link href="/" class="hover:text-[var(--brand-primary)] hover:underline">Home</Link>
                <span>/</span>
                <Link href="/products" class="hover:text-[var(--brand-primary)] hover:underline">Products</Link>
                <span>/</span>
                <span class="font-medium text-[var(--text-primary)]">Order confirmation</span>
            </nav>

            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-[var(--border-soft)] bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[var(--brand-primary-soft)] text-lg text-[var(--brand-primary)]">✓</span>
                    <div>
                        <p class="text-xs text-[var(--text-muted)]">Thank you — order received</p>
                        <h1 class="mt-0.5 text-xl font-bold">{{ order.order_number }}</h1>
                        <p class="mt-1 text-sm text-[var(--text-secondary)]">
                            Placed {{ order.created_at ?? '-' }} · {{ order.items.length }} item{{ order.items.length === 1 ? '' : 's' }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-[var(--bg-muted)] px-3 py-1 font-semibold text-[var(--text-secondary)]">
                        Order: {{ order.status }}
                    </span>
                    <span
                        v-if="paymentStatus"
                        class="rounded-full px-3 py-1 font-semibold"
                        :class="
                            paymentStatus === 'paid'
                                ? 'bg-emerald-100 text-emerald-700'
                                : paymentStatus === 'failed'
                                  ? 'bg-red-100 text-red-700'
                                  : 'bg-amber-100 text-amber-800'
                        "
                    >
                        Payment: {{ paymentStatus }} · {{ paymentLabel }}
                    </span>
                </div>
            </div>

            <div
                v-if="order.is_expired"
                class="mt-4 rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm"
            >
                <p class="text-sm font-semibold text-red-700">
                    This order has expired
                </p>
                <p class="mt-1 text-sm text-red-600">
                    The payment window closed before we received your receipt, so the
                    reserved stock was released. Please place a new order to continue.
                </p>
                <Link
                    href="/products"
                    class="mt-3 inline-block h-10 rounded-md bg-[var(--brand-primary)] px-5 text-sm font-semibold leading-10 text-white transition hover:bg-[var(--brand-primary-hover)]"
                >
                    Shop again
                </Link>
            </div>

            <div class="mt-5 grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="space-y-5">
                    <section class="rounded-xl border border-[var(--border-soft)] bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold">Payment instructions</h2>
                        <p v-if="paymentStatus === 'paid'" class="mt-2 text-sm text-emerald-700">
                            Payment verified. We are preparing your order.
                        </p>
                        <p v-else-if="paymentStatus === 'failed'" class="mt-2 text-sm text-red-700">
                            The last payment attempt failed. Please place a new order or contact the store.
                        </p>
                        <p v-else class="mt-2 text-sm text-[var(--text-secondary)]">
                            <template v-if="isManualPayment">
                                Complete the manual transfer below, then upload your receipt.
                                Admin verifies it before the order moves to paid.
                            </template>
                            <template v-else>
                                Check again shortly — PayNet confirmation may still arrive.
                            </template>
                        </p>

                        <div
                            v-if="isManualPayment"
                            class="mt-4 rounded-lg border border-[var(--border-soft)] bg-[var(--bg-muted)] p-4 text-sm"
                        >
                            <p v-if="isBankTransfer" class="font-semibold">Bank transfer details</p>
                            <p v-else-if="isQrManual" class="font-semibold">QR code payment</p>
                            <p v-else class="font-semibold">Manual payment</p>

                            <dl v-if="isBankTransfer" class="mt-2 space-y-1.5">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-[var(--text-muted)]">Bank</dt>
                                    <dd class="font-medium">{{ bankName || '-' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-[var(--text-muted)]">Account name</dt>
                                    <dd class="font-medium">{{ bankAccountName || '-' }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-[var(--text-muted)]">Account number</dt>
                                    <dd class="font-medium">{{ bankAccountNumber || '-' }}</dd>
                                </div>
                                <p class="mt-1 text-xs text-[var(--text-muted)]">
                                    Transfer exactly {{ formatAmount(Number(order.total)) }} so admin can match it.
                                </p>
                            </dl>

                            <div v-if="isQrManual" class="mt-3">
                                <img
                                    v-if="qrCodeUrl"
                                    :src="qrCodeUrl"
                                    alt="Payment QR code"
                                    class="h-48 w-48 rounded-lg border border-[var(--border-soft)] bg-white object-contain"
                                />
                                <p v-else class="text-[var(--text-secondary)]">
                                    QR code image has not been set. Please contact the store.
                                </p>
                            </div>
                        </div>

                        <div v-else class="mt-4 rounded-lg border border-[var(--border-soft)] bg-[var(--bg-muted)] p-4 text-sm">
                            <a
                                v-if="redirectUrl"
                                :href="redirectUrl"
                                class="font-semibold text-[var(--brand-primary)] underline"
                            >
                                Continue to PayNet to complete payment
                            </a>
                            <p v-if="qrPayload" class="mt-1 break-all">
                                DuitNow QR reference:
                                <span class="font-medium">{{ qrPayload }}</span>
                            </p>
                            <p v-if="!redirectUrl && !qrPayload" class="text-[var(--text-secondary)]">
                                Your PayNet session link will appear here once created.
                            </p>
                        </div>

                        <div v-if="isManualPayment" class="mt-4 border-t border-[var(--border-soft)] pt-4">
                            <h3 class="text-sm font-semibold">Payment receipt</h3>
                            <p class="mt-1 text-xs text-[var(--text-muted)]">
                                JPG, PNG, or WebP up to 5 MB. You can re-upload while the payment is pending.
                            </p>

                            <div v-if="proofUrl" class="mt-3 flex flex-wrap items-start gap-3">
                                <a :href="proofUrl" target="_blank" rel="noopener">
                                    <img
                                        :src="proofUrl"
                                        alt="Uploaded payment receipt"
                                        class="h-28 w-28 rounded-lg border border-[var(--border-soft)] bg-white object-cover"
                                    />
                                </a>
                                <div class="text-xs text-[var(--text-muted)]">
                                    <p class="font-semibold text-[var(--text-primary)]">Receipt uploaded</p>
                                    <p v-if="proofUploadedAt">{{ proofUploadedAt }}</p>
                                    <a :href="proofUrl" target="_blank" rel="noopener" class="text-[var(--brand-primary)] underline">
                                        View full image
                                    </a>
                                </div>
                            </div>

                            <div v-if="canUploadProof" class="mt-3 space-y-2">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-[var(--border-default)] file:bg-white file:px-3 file:py-2 file:text-sm"
                                    @change="onFileChange"
                                />
                                <img
                                    v-if="previewUrl"
                                    :src="previewUrl"
                                    alt="Receipt preview"
                                    class="h-28 w-28 rounded-lg border border-[var(--border-soft)] bg-white object-cover"
                                />
                                <p v-if="uploadError" class="text-xs text-red-600">{{ uploadError }}</p>
                                <p v-if="uploadSuccess" class="text-xs text-emerald-700">{{ uploadSuccess }}</p>
                                <button
                                    type="button"
                                    :disabled="isUploading || !selectedFile"
                                    class="h-10 rounded-md bg-[var(--brand-primary)] px-5 text-sm font-semibold text-white transition hover:bg-[var(--brand-primary-hover)] disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="uploadProof"
                                >
                                    {{ isUploading ? 'Uploading…' : proofUrl ? 'Replace receipt' : 'Upload receipt' }}
                                </button>
                            </div>
                            <p v-else-if="order.is_expired" class="mt-3 text-xs text-[var(--text-muted)]">
                                Receipt upload is closed because this order has expired.
                            </p>
                            <p v-else class="mt-3 text-xs text-[var(--text-muted)]">
                                Receipt upload is closed because this payment is {{ paymentStatus }}.
                            </p>
                        </div>

                        <p v-if="order.is_expired" class="mt-4 text-xs text-red-600">
                            The payment window closed on {{ order.expired_at }}.
                        </p>
                        <p v-else-if="order.expired_at" class="mt-4 text-xs text-[var(--text-muted)]">
                            Complete payment before {{ order.expired_at }}.
                        </p>
                    </section>

                    <section class="rounded-xl border border-[var(--border-soft)] bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold">Items ({{ order.items.length }})</h2>
                        <ul class="mt-3 divide-y divide-[var(--border-soft)] overflow-hidden rounded-lg border border-[var(--border-soft)]">
                            <li
                                v-for="(item, index) in order.items"
                                :key="index"
                                class="flex items-start justify-between gap-3 p-3 text-sm"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ item.product_name }}</span>
                                    <span class="text-xs text-[var(--text-muted)]">
                                        {{ formatAmount(Number(item.price)) }} × {{ item.quantity }}
                                    </span>
                                </span>
                                <span class="shrink-0 font-semibold">{{ formatAmount(Number(item.subtotal)) }}</span>
                            </li>
                        </ul>
                    </section>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-24">
                    <section class="rounded-xl border border-[var(--border-soft)] bg-white p-5 shadow-sm">
                        <h2 class="text-sm font-semibold">Order summary</h2>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-[var(--text-muted)]">Customer</dt>
                                <dd class="text-right font-medium">{{ order.customer_name }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-[var(--text-muted)]">Subtotal</dt>
                                <dd>{{ formatAmount(Number(order.subtotal)) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-[var(--text-muted)]">Shipping ({{ order.shipping_method }})</dt>
                                <dd>{{ formatAmount(Number(order.shipping_fee)) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 border-t border-[var(--border-soft)] pt-2 text-base font-bold text-[var(--brand-primary)]">
                                <dt>Total</dt>
                                <dd>{{ formatAmount(Number(order.total)) }}</dd>
                            </div>
                        </dl>
                        <Link
                            href="/products"
                            class="mt-4 inline-block h-11 w-full rounded-md bg-[var(--brand-primary)] px-5 text-center text-sm font-semibold leading-[44px] text-white transition hover:bg-[var(--brand-primary-hover)]"
                        >
                            Continue shopping
                        </Link>
                    </section>

                    <section class="rounded-xl border border-[var(--border-soft)] bg-white p-5 text-sm shadow-sm">
                        <h2 class="text-sm font-semibold">Delivery</h2>
                        <p class="mt-2 text-[var(--text-secondary)]">
                            {{
                                fullAddress(
                                    order.shipping_address,
                                    order.shipping_city,
                                    order.shipping_state,
                                    order.shipping_post_code,
                                )
                            }}
                        </p>
                        <p v-if="order.shipping_provider" class="mt-1 text-xs text-[var(--text-muted)]">
                            {{ order.shipping_method }} · {{ order.shipping_provider }}
                        </p>
                    </section>

                    <template v-for="tracking in shippableTrackings" :key="tracking.seller_id">
                        <section class="rounded-xl border border-[var(--border-soft)] bg-white p-5 text-sm shadow-sm">
                            <h2 class="text-sm font-semibold">Tracking {{ tracking.seller_name ? `- ${tracking.seller_name}` : '' }}</h2>
                            <dl class="mt-2 space-y-1.5">
                                <div v-if="tracking.courier" class="flex justify-between gap-3">
                                    <dt class="text-[var(--text-muted)]">Courier</dt>
                                    <dd class="font-medium">{{ tracking.courier }}</dd>
                                </div>
                                <div v-if="tracking.waybill_number" class="flex justify-between gap-3">
                                    <dt class="text-[var(--text-muted)]">Waybill number</dt>
                                    <dd class="text-right font-medium">{{ tracking.waybill_number }}</dd>
                                </div>
                                <div v-if="tracking.tracking_status" class="flex justify-between gap-3">
                                    <dt class="text-[var(--text-muted)]">Status</dt>
                                    <dd class="font-medium">{{ tracking.tracking_status }}</dd>
                                </div>
                            </dl>
                            <a v-if="tracking.tracking_url" :href="tracking.tracking_url" target="_blank" rel="noopener" class="mt-3 inline-block font-semibold text-[var(--brand-primary)] underline">
                                Track shipment
                            </a>
                        </section>
                    </template>

                    <section class="rounded-xl border border-[var(--border-soft)] bg-white p-5 text-sm shadow-sm">
                        <h2 class="text-sm font-semibold">Billing contact</h2>
                        <p class="mt-2 font-medium">{{ order.customer_name }}</p>
                        <p class="text-[var(--text-secondary)]">
                            {{
                                fullAddress(
                                    order.customer_address,
                                    order.customer_city,
                                    order.customer_state,
                                    order.customer_post_code,
                                )
                            }}
                        </p>
                        <p v-if="order.customer_phone" class="mt-1">{{ order.customer_phone }}</p>
                        <p v-if="order.customer_email" class="mt-1 text-[var(--text-muted)]">{{ order.customer_email }}</p>
                    </section>
                </aside>
            </div>
        </div>
    </MarketplaceLayout>
</template>
