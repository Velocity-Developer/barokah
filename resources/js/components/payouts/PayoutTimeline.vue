<script setup lang="ts">
import { computed } from 'vue';
import { formatPayoutDate, type PayoutRow } from './types';

const props = defineProps<{ payout: PayoutRow }>();

type Step = { key: string; title: string; at: string | null; done: boolean; failed?: boolean; detail?: string | null };

// Rejection replaces the remaining steps, so the line never shows a step that cannot happen.
const steps = computed<Step[]>(() => {
    const payout = props.payout;
    const requested: Step = { key: 'requested', title: 'Requested', at: payout.requested_at, done: true, detail: payout.seller_note };

    if (payout.status === 'rejected') {
        return [requested, { key: 'rejected', title: 'Rejected', at: payout.rejected_at, done: true, failed: true, detail: payout.admin_note }];
    }

    return [
        requested,
        { key: 'approved', title: 'Approved by admin', at: payout.approved_at, done: payout.approved_at !== null },
        {
            key: 'paid',
            title: 'Transfer sent',
            at: payout.paid_at,
            done: payout.status === 'paid',
            detail: [payout.transfer_reference ? `Ref. ${payout.transfer_reference}` : null, payout.admin_note].filter(Boolean).join(' · ') || null,
        },
    ];
});
</script>

<template>
    <ol class="grid gap-0">
        <li v-for="(step, index) in steps" :key="step.key" class="relative flex gap-3 pb-4 last:pb-0">
            <span
                v-if="index < steps.length - 1"
                class="absolute top-4 left-[7px] h-full w-px"
                :class="steps[index + 1].done ? 'bg-emerald-500' : 'bg-border'"
                aria-hidden="true"
            />
            <span
                class="relative mt-0.5 size-[15px] shrink-0 rounded-full border-2"
                :class="step.failed ? 'border-red-500 bg-red-500' : step.done ? 'border-emerald-500 bg-emerald-500' : 'border-border bg-background'"
                aria-hidden="true"
            />
            <div class="min-w-0 text-sm">
                <p :class="step.done ? 'font-medium' : 'text-muted-foreground'">{{ step.title }}</p>
                <p v-if="step.at" class="text-xs text-muted-foreground">{{ formatPayoutDate(step.at) }}</p>
                <p v-if="step.detail" class="mt-0.5 text-xs break-words" :class="step.failed ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'">
                    {{ step.detail }}
                </p>
            </div>
        </li>
    </ol>
</template>
