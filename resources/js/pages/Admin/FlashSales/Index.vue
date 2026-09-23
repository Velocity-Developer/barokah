<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FlashSaleList from '@/components/flash-sales/FlashSaleList.vue';
import { index } from '@/routes/admin/flash-sales';

type Paginated = { data: Record<string, unknown>[]; [key: string]: unknown };

defineProps<{
    flashSales: Paginated;
    filters: { search: string; status: string; sort: string };
    statusCounts: Record<string, number>;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Flash sales', href: index() }] } });
</script>

<template>
    <Head title="Flash sales" />
    <FlashSaleList mode="admin" :flash-sales="flashSales as never" :filters="filters" :status-counts="statusCounts" />
</template>
