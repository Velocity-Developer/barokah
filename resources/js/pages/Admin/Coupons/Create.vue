<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CouponForm from '@/components/coupons/CouponForm.vue';

defineProps<{ sellers: { id: number; store_name: string }[] }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Coupons', href: '/admin/coupons' }] } });
</script>

<template>
    <Head title="New coupon" />
    <CouponForm mode="admin" :sellers="sellers" />
</template>
