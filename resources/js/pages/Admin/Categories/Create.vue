<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryForm from '@/components/admin/CategoryForm.vue';
import { create, index } from '@/routes/admin/categories';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Categories', href: index() },
            { title: 'New category', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New category" />
    <CategoryForm />
</template>
