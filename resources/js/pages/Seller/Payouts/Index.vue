<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Banknote, Clock, ExternalLink, Landmark, Send, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PayoutStatusBadge from '@/components/payouts/PayoutStatusBadge.vue';
import PayoutTimeline from '@/components/payouts/PayoutTimeline.vue';
import { formatPayoutDate, type PayoutRow, type PayoutShipment } from '@/components/payouts/types';
import { settings as sellerSettings } from '@/routes/seller';
import { index, store } from '@/routes/seller/payouts';

const props = defineProps<{
    balance: {
        orders_count: number;
        product_formatted: string;
        shipping_formatted: string;
        commission_rate: number;
        commission_formatted: string;
        net: number;
        net_formatted: string;
        in_process_formatted: string;
        paid_out_formatted: string;
    };
    bank_account: string | null;
    available_orders: PayoutShipment[];
    payouts: PayoutRow[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Payouts', href: index() }] } });

const form = useForm({ note: '' });

const hasBank = computed(() => (props.bank_account ?? '').trim() !== '');
const canRequest = computed(() => hasBank.value && props.balance.net > 0 && props.balance.orders_count > 0);
const openPayout = computed(() => props.payouts.find((payout) => payout.status === 'pending' || payout.status === 'approved'));

function requestPayout(): void {
    if (!window.confirm(`Request a payout of ${props.balance.net_formatted} for ${props.balance.orders_count} delivered order(s)?`)) {
        return;
    }

    form.post(store().url, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <Head title="Payouts" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading
            variant="small"
            title="Payouts"
            description="Money from delivered orders. Request a payout, the marketplace transfers it to your bank and uploads the proof here."
        />

        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-2 text-sm text-muted-foreground"><Wallet class="size-4" aria-hidden="true" /> Available to withdraw</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ balance.net_formatted }}</p>
                <p class="text-xs text-muted-foreground">{{ balance.orders_count }} delivered order(s)</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-2 text-sm text-muted-foreground"><Clock class="size-4" aria-hidden="true" /> In process</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ balance.in_process_formatted }}</p>
                <p class="text-xs text-muted-foreground">Requested, not sent yet</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-2 text-sm text-muted-foreground"><Banknote class="size-4" aria-hidden="true" /> Paid out</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ balance.paid_out_formatted }}</p>
                <p class="text-xs text-muted-foreground">All transfers sent to you</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="font-medium">Request a payout</h2>

                <dl class="grid gap-1.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Product sales</dt>
                        <dd class="tabular-nums">{{ balance.product_formatted }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Commission ({{ balance.commission_rate }}%)</dt>
                        <dd class="tabular-nums">− {{ balance.commission_formatted }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Shipping fees</dt>
                        <dd class="tabular-nums">+ {{ balance.shipping_formatted }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t pt-1.5 font-medium">
                        <dt>You receive</dt>
                        <dd class="tabular-nums">{{ balance.net_formatted }}</dd>
                    </div>
                </dl>

                <div class="flex items-start gap-2 rounded-lg border bg-muted/40 p-3 text-sm">
                    <Landmark class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <div class="min-w-0">
                        <p class="text-muted-foreground">Paid to</p>
                        <p v-if="hasBank" class="break-words whitespace-pre-line">{{ bank_account }}</p>
                        <p v-else class="text-amber-700 dark:text-amber-300">No bank account yet.</p>
                        <Link :href="sellerSettings()" class="text-xs font-medium text-[var(--brand-primary,#8b3fa8)] hover:underline">
                            {{ hasBank ? 'Change in Store settings' : 'Add it in Store settings' }}
                        </Link>
                    </div>
                </div>

                <label class="grid gap-1.5 text-sm">
                    <span>Note for the admin <span class="text-muted-foreground">(optional)</span></span>
                    <textarea v-model="form.note" rows="2" maxlength="1000" class="rounded-md border bg-background px-3 py-2 text-sm" />
                </label>
                <InputError :message="(form.errors as Record<string, string | undefined>).payout ?? form.errors.note" />

                <button
                    type="button"
                    class="inline-flex h-9 w-fit items-center gap-1.5 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    :disabled="!canRequest || form.processing"
                    @click="requestPayout"
                >
                    <Send class="size-4" aria-hidden="true" /> Request {{ balance.net_formatted }}
                </button>
                <p v-if="!canRequest && hasBank" class="text-xs text-muted-foreground">
                    Orders become available once you mark them Delivered.
                    <template v-if="openPayout">Your request {{ openPayout.reference }} is still being processed.</template>
                </p>
            </section>

            <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="font-medium">Orders in this balance</h2>
                <p v-if="available_orders.length === 0" class="text-sm text-muted-foreground">No delivered orders waiting for a payout.</p>
                <ul v-else class="grid max-h-80 gap-0 overflow-y-auto text-sm">
                    <li v-for="order in available_orders" :key="order.id" class="flex items-center justify-between gap-3 border-b py-2 last:border-0">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ order.order_number }}</p>
                            <p class="text-xs text-muted-foreground">Delivered {{ formatPayoutDate(order.delivered_at) }}</p>
                        </div>
                        <span class="shrink-0 tabular-nums">{{ order.total_formatted }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <section class="grid gap-3">
            <h2 class="font-medium">Payout history</h2>
            <p v-if="payouts.length === 0" class="rounded-xl border bg-card p-6 text-center text-sm text-muted-foreground shadow-sm">
                No payout requests yet.
            </p>
            <article v-for="payout in payouts" :key="payout.id" class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_260px]">
                <div class="grid content-start gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ payout.reference }}</span>
                        <PayoutStatusBadge :status="payout.status" />
                    </div>
                    <p class="text-2xl font-semibold tabular-nums">{{ payout.net_amount_formatted }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ payout.orders_count }} order(s) · products {{ payout.product_amount_formatted }} − commission {{ payout.commission_amount_formatted }}
                        ({{ payout.commission_rate }}%) + shipping {{ payout.shipping_amount_formatted }}
                    </p>
                    <p class="text-xs break-words whitespace-pre-line text-muted-foreground">To: {{ payout.bank_account }}</p>
                    <a
                        v-if="payout.proof_url"
                        :href="payout.proof_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-8 w-fit items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                    >
                        <ExternalLink class="size-4" aria-hidden="true" /> View transfer proof
                    </a>
                </div>
                <PayoutTimeline :payout="payout" />
            </article>
        </section>
    </div>
</template>
