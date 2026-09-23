<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CouponForm, { type CouponValue } from '@/components/coupons/CouponForm.vue';

defineProps<{ coupon: CouponValue }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Coupons', href: '/seller/coupons' }] } });
</script>

<template>
    <Head title="Edit coupon" />
    <CouponForm mode="seller" :coupon="coupon" />
</template>
