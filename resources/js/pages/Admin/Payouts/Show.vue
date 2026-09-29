<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, ExternalLink, Landmark, Mail, MessageCircle, Phone, Upload, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PayoutStatusBadge from '@/components/payouts/PayoutStatusBadge.vue';
import PayoutTimeline from '@/components/payouts/PayoutTimeline.vue';
import { formatPayoutDate, type PayoutRow, type PayoutShipment } from '@/components/payouts/types';
import { show as orderShow } from '@/routes/admin/orders';
import { approve, index, paid, reject } from '@/routes/admin/payouts';
import { show as sellerShow } from '@/routes/admin/sellers';

const props = defineProps<{
    payout: PayoutRow & {
        processed_by: string | null;
        seller: {
            id: number;
            store_name: string;
            owner_name: string | null;
            owner_email: string | null;
            phone: string | null;
            whatsapp: string | null;
            current_bank_account: string | null;
        };
    };
    orders: PayoutShipment[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Payouts', href: index() }] } });

const approving = ref(false);
const showReject = ref(false);
const rejectForm = useForm({ reason: '' });
const paidForm = useForm<{ proof: File | null; transfer_reference: string; note: string }>({ proof: null, transfer_reference: '', note: '' });

const isOpen = computed(() => props.payout.status === 'pending' || props.payout.status === 'approved');
const bankChanged = computed(
    () => (props.payout.seller.current_bank_account ?? '').trim() !== '' && props.payout.seller.current_bank_account?.trim() !== props.payout.bank_account.trim(),
);

function approvePayout(): void {
    if (!window.confirm(`Approve ${props.payout.reference} for ${props.payout.net_amount_formatted}?`)) {
        return;
    }

    router.post(approve(props.payout.id).url, {}, {
        preserveScroll: true,
        onStart: () => (approving.value = true),
        onFinish: () => (approving.value = false),
    });
}

function rejectPayout(): void {
    rejectForm.post(reject(props.payout.id).url, { preserveScroll: true, onSuccess: () => (showReject.value = false) });
}

function markPaid(): void {
    paidForm.post(paid(props.payout.id).url, { preserveScroll: true, forceFormData: true });
}

function whatsappUrl(number: string | null): string | null {
    const digits = (number ?? '').replace(/\D/g, '');

    return digits ? `https://wa.me/${digits}` : null;
}
</script>

<template>
    <Head :title="payout.reference" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <Heading variant="small" :title="`Payout ${payout.reference}`" :description="`Requested ${formatPayoutDate(payout.requested_at)} by ${payout.seller.store_name}`" />
            <PayoutStatusBadge :status="payout.status" class="w-fit" />
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="grid content-start gap-4">
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="font-medium">Amount to transfer</h2>
                    <p class="text-3xl font-semibold tabular-nums">{{ payout.net_amount_formatted }}</p>
                    <dl class="grid gap-1.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Product sales ({{ payout.orders_count }} orders)</dt>
                            <dd class="tabular-nums">{{ payout.product_amount_formatted }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Commission ({{ payout.commission_rate }}%)</dt>
                            <dd class="tabular-nums">− {{ payout.commission_amount_formatted }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Shipping fees</dt>
                            <dd class="tabular-nums">+ {{ payout.shipping_amount_formatted }}</dd>
                        </div>
                    </dl>

                    <div class="flex items-start gap-2 rounded-lg border bg-muted/40 p-3 text-sm">
                        <Landmark class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <div class="min-w-0">
                            <p class="text-muted-foreground">Transfer to</p>
                            <p class="font-medium break-words whitespace-pre-line">{{ payout.bank_account }}</p>
                            <p v-if="bankChanged" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                The store has since changed its account to: {{ payout.seller.current_bank_account }}
                            </p>
                        </div>
                    </div>

                    <p v-if="payout.seller_note" class="text-sm"><span class="text-muted-foreground">Note from the store:</span> {{ payout.seller_note }}</p>
                </section>

                <!-- Step 1: decide. -->
                <section v-if="payout.status === 'pending'" class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="font-medium">Review the request</h2>
                    <p class="text-sm text-muted-foreground">Check the orders below. After approving, transfer the money from your bank and upload the proof here.</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                            :disabled="approving"
                            @click="approvePayout"
                        >
                            <Check class="size-4" aria-hidden="true" /> Approve
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-red-200 px-3 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                            @click="showReject = !showReject"
                        >
                            <X class="size-4" aria-hidden="true" /> Reject
                        </button>
                    </div>
                </section>

                <!-- Step 2: the transfer happened outside the site; record it. -->
                <section v-if="payout.status === 'approved'" class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="font-medium">Record the transfer</h2>
                    <p class="text-sm text-muted-foreground">
                        Send {{ payout.net_amount_formatted }} to the account above from your bank, then upload the receipt. The store sees it right away.
                    </p>
                    <div class="grid content-start gap-3 md:grid-cols-2">
                        <label class="grid content-start gap-1.5 text-sm">
                            <span>Transfer proof <span class="text-muted-foreground">(image or PDF, max 5 MB)</span></span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                class="rounded-md border bg-background px-3 py-1.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-2 file:py-1 file:text-sm"
                                @change="paidForm.proof = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <InputError :message="paidForm.errors.proof" />
                        </label>
                        <label class="grid content-start gap-1.5 text-sm">
                            <span>Bank reference <span class="text-muted-foreground">(optional)</span></span>
                            <input v-model="paidForm.transfer_reference" type="text" maxlength="255" class="h-9 rounded-md border bg-background px-3 text-sm" />
                            <InputError :message="paidForm.errors.transfer_reference" />
                        </label>
                    </div>
                    <label class="grid gap-1.5 text-sm">
                        <span>Note for the store <span class="text-muted-foreground">(optional)</span></span>
                        <textarea v-model="paidForm.note" rows="2" maxlength="1000" class="rounded-md border bg-background px-3 py-2 text-sm" />
                    </label>
                    <InputError :message="(paidForm.errors as Record<string, string | undefined>).payout" />
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                            :disabled="!paidForm.proof || paidForm.processing"
                            @click="markPaid"
                        >
                            <Upload class="size-4" aria-hidden="true" /> Mark as sent
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-red-200 px-3 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                            @click="showReject = !showReject"
                        >
                            <X class="size-4" aria-hidden="true" /> Reject
                        </button>
                    </div>
                </section>

                <section v-if="isOpen && showReject" class="grid gap-3 rounded-xl border border-red-200 bg-card p-4 shadow-sm dark:border-red-900">
                    <h2 class="font-medium">Reject this payout</h2>
                    <p class="text-sm text-muted-foreground">The orders go back to the store balance so it can request again. The store sees your reason.</p>
                    <textarea
                        v-model="rejectForm.reason"
                        rows="2"
                        maxlength="1000"
                        placeholder="e.g. The account holder name does not match the store owner."
                        class="rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="rejectForm.errors.reason ?? (rejectForm.errors as Record<string, string | undefined>).payout" />
                    <button
                        type="button"
                        class="inline-flex h-9 w-fit items-center gap-1.5 rounded-md bg-red-600 px-3 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="rejectForm.reason.trim() === '' || rejectForm.processing"
                        @click="rejectPayout"
                    >
                        Reject payout
                    </button>
                </section>

                <section class="grid gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="font-medium">Orders in this payout</h2>
                    <p v-if="orders.length === 0" class="text-sm text-muted-foreground">
                        {{ payout.status === 'rejected' ? 'Released back to the store balance.' : 'No orders.' }}
                    </p>
                    <!-- A payout can hold hundreds of orders, so the list scrolls on its own. -->
                    <div v-else class="max-h-[28rem] overflow-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead class="sticky top-0 bg-card">
                                <tr class="border-b text-xs text-muted-foreground">
                                    <th class="py-2 pr-4 font-medium">Order</th>
                                    <th class="py-2 pr-4 font-medium">Delivered</th>
                                    <th class="py-2 pr-4 text-right font-medium">Products</th>
                                    <th class="py-2 pr-4 text-right font-medium">Shipping</th>
                                    <th class="py-2 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in orders" :key="order.id" class="border-b last:border-0">
                                    <td class="py-2 pr-4">
                                        <Link :href="orderShow(order.order_number)" class="font-medium hover:underline">{{ order.order_number }}</Link>
                                    </td>
                                    <td class="py-2 pr-4 text-muted-foreground">{{ formatPayoutDate(order.delivered_at) }}</td>
                                    <td class="py-2 pr-4 text-right tabular-nums">{{ order.product_amount_formatted }}</td>
                                    <td class="py-2 pr-4 text-right tabular-nums">{{ order.shipping_amount_formatted }}</td>
                                    <td class="py-2 text-right font-medium tabular-nums">{{ order.total_formatted }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="font-medium">Tracking</h2>
                    <PayoutTimeline :payout="payout" />
                    <p v-if="payout.processed_by" class="text-xs text-muted-foreground">Last handled by {{ payout.processed_by }}</p>
                    <a
                        v-if="payout.proof_url"
                        :href="payout.proof_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-8 w-fit items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        <ExternalLink class="size-4" aria-hidden="true" /> View transfer proof
                    </a>
                </section>

                <section class="grid gap-2 rounded-xl border bg-card p-4 text-sm shadow-sm">
                    <h2 class="font-medium">Store</h2>
                    <Link :href="sellerShow(payout.seller.id)" class="font-medium hover:underline">{{ payout.seller.store_name }}</Link>
                    <p v-if="payout.seller.owner_name" class="text-muted-foreground">{{ payout.seller.owner_name }}</p>
                    <p v-if="payout.seller.owner_email" class="flex items-center gap-2">
                        <Mail class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <a :href="`mailto:${payout.seller.owner_email}`" class="truncate hover:underline">{{ payout.seller.owner_email }}</a>
                    </p>
                    <p v-if="payout.seller.phone" class="flex items-center gap-2">
                        <Phone class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" /> {{ payout.seller.phone }}
                    </p>
                    <p v-if="payout.seller.whatsapp" class="flex items-center gap-2">
                        <MessageCircle class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <a v-if="whatsappUrl(payout.seller.whatsapp)" :href="whatsappUrl(payout.seller.whatsapp)!" target="_blank" rel="noopener" class="hover:underline">
                            {{ payout.seller.whatsapp }}
                        </a>
                        <template v-else>{{ payout.seller.whatsapp }}</template>
                    </p>
                </section>
            </aside>
        </div>
    </div>
</template>
