<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FlashSaleForm, { type FlashSaleValue } from '@/components/flash-sales/FlashSaleForm.vue';
import { index } from '@/routes/admin/flash-sales';

defineProps<{ flashSale: FlashSaleValue }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Flash sales', href: index() }] } });
</script>

<template>
    <Head title="Edit flash sale" />
    <FlashSaleForm mode="admin" :flash-sale="flashSale" />
</template>
