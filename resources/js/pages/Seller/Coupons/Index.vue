<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CouponList from '@/components/coupons/CouponList.vue';

type Paginated = { data: Record<string, unknown>[]; [key: string]: unknown };

defineProps<{
    coupons: Paginated;
    filters: { search: string; status: string; owner: string; sort: string };
    statusCounts: Record<string, number>;
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Coupons', href: '/seller/coupons' }] } });
</script>

<template>
    <Head title="Coupons" />
    <CouponList mode="seller" :coupons="coupons as never" :filters="filters" :status-counts="statusCounts" />
</template>
