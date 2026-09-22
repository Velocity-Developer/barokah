<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryForm, { type CategoryFormValue } from '@/components/admin/CategoryForm.vue';
import { index } from '@/routes/admin/categories';

defineProps<{
    category: CategoryFormValue;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categories', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Edit ${category.name}`" />
    <CategoryForm :category="category" />
</template>
