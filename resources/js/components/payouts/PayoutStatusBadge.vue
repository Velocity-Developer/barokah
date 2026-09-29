<script setup lang="ts">
import { payoutStatusLabels, type PayoutStatus } from './types';

defineProps<{ status: PayoutStatus }>();

const styles: Record<PayoutStatus, string> = {
    pending: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-900',
    approved: 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-950 dark:text-sky-200 dark:ring-sky-900',
    paid: 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-900',
    rejected: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
};
</script>

<template>
    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset" :class="styles[status]">
        {{ payoutStatusLabels[status] }}
    </span>
</template>
