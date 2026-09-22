<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import FlashSaleForm, { type FlashSaleValue } from '@/components/admin/FlashSaleForm.vue';
import { index } from '@/routes/admin/flash-sales';

defineProps<{ flashSale: FlashSaleValue }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Flash sales', href: index() }] } });
</script>

<template>
    <Head title="Edit flash sale" />
    <FlashSaleForm :flash-sale="flashSale" />
</template>
