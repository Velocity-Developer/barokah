<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CouponForm, { type CouponValue } from '@/components/coupons/CouponForm.vue';

defineProps<{ coupon: CouponValue; sellers: { id: number; store_name: string }[] }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Coupons', href: '/admin/coupons' }] } });
</script>

<template>
    <Head title="Edit coupon" />
    <CouponForm mode="admin" :coupon="coupon" :sellers="sellers" />
</template>
