<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FlashSaleForm, { type FlashSaleProduct } from '@/components/flash-sales/FlashSaleForm.vue';
import { index } from '@/routes/admin/flash-sales';

defineProps<{ products: FlashSaleProduct[] }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Flash sales', href: index() }] } });
</script>

<template>
    <Head title="New flash sale" />
    <FlashSaleForm mode="admin" :products="products" />
</template>
