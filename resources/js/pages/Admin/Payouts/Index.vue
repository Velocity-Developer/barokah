<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import PayoutStatusBadge from '@/components/payouts/PayoutStatusBadge.vue';
import { formatPayoutDate, type PayoutRow } from '@/components/payouts/types';
import { index, show } from '@/routes/admin/payouts';

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

defineProps<{
    payouts: Paginated<PayoutRow>;
    filters: { status: string };
    status_counts: Record<string, number>;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Payouts', href: index() }] } });

const STATUS_TABS = [
    { value: '', label: 'All', count: 'all' },
    { value: 'pending', label: 'Waiting for approval', count: 'pending' },
    { value: 'approved', label: 'Transfer pending', count: 'approved' },
    { value: 'paid', label: 'Sent', count: 'paid' },
    { value: 'rejected', label: 'Rejected', count: 'rejected' },
];

function setStatus(status: string): void {
    router.get(index().url, status ? { status } : {}, { preserveScroll: true, preserveState: true });
}

function pageLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

</script>

<template>
    <Head title="Payouts" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Heading
            variant="small"
            title="Payouts"
            description="Stores ask to be paid for delivered orders. Approve, transfer the money from your bank, then upload the proof."
        />

        <section class="rounded-xl border bg-card shadow-sm">
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Payout status">
                <button
                    v-for="tab in STATUS_TABS"
                    :key="tab.value"
                    type="button"
                    class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition"
                    :class="filters.status === tab.value ? 'border-[var(--brand-primary,#ee4d2d)] font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    :aria-pressed="filters.status === tab.value"
                    @click="setStatus(tab.value)"
                >
                    {{ tab.label }}
                    <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">{{ status_counts[tab.count] ?? 0 }}</span>
                </button>
            </nav>

            <div v-if="payouts.data.length === 0" class="p-10 text-center text-sm text-muted-foreground">No payout requests here.</div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs text-muted-foreground">
                            <th class="px-4 py-2 font-medium">Payout</th>
                            <th class="px-4 py-2 font-medium">Store</th>
                            <th class="px-4 py-2 font-medium">Orders</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 text-right font-medium">Amount</th>
                            <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payout in payouts.data" :key="payout.id" class="border-b last:border-0 hover:bg-muted/50">
                            <td class="px-4 py-2.5">
                                <Link :href="show(payout.id)" class="font-medium hover:underline">{{ payout.reference }}</Link>
                                <p class="text-xs text-muted-foreground">{{ formatPayoutDate(payout.requested_at) }}</p>
                            </td>
                            <td class="px-4 py-2.5">{{ payout.store_name ?? '—' }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ payout.orders_count }}</td>
                            <td class="px-4 py-2.5"><PayoutStatusBadge :status="payout.status" /></td>
                            <td class="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">{{ payout.net_amount_formatted }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <Link :href="show(payout.id)" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground" :aria-label="`Open ${payout.reference}`">
                                    Open <ArrowRight class="size-3.5" aria-hidden="true" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="payouts.links.length > 3" class="flex flex-wrap gap-1 border-t p-3" aria-label="Pages">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in payouts.links"
                    :key="i"
                    :href="link.url ?? undefined"
                    preserve-scroll
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm"
                    :class="link.active ? 'bg-muted font-medium' : link.url ? 'hover:bg-muted' : 'opacity-50'"
                >
                    {{ pageLabel(link.label) }}
                </component>
            </nav>
        </section>
    </div>
</template>
