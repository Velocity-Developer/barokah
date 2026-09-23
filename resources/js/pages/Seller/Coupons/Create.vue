<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CouponForm from '@/components/coupons/CouponForm.vue';

defineOptions({ layout: { breadcrumbs: [{ title: 'Coupons', href: '/seller/coupons' }] } });
</script>

<template>
    <Head title="New coupon" />
    <CouponForm mode="seller" />
</template>
