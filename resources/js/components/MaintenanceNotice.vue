<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as adminSettingsShow } from '@/routes/admin/settings';

const page = usePage();

// Shared only to admins and sellers, the people who can still get in.
const isOn = computed(() => page.props.maintenance_mode === true);
const isAdmin = computed(() => page.props.auth?.can?.admin === true);
</script>

<template>
    <div v-if="isOn" class="bg-amber-400 px-4 py-1.5 text-center text-xs font-medium text-amber-950" role="status">
        Maintenance mode is on: shoppers see the maintenance page.
        <Link v-if="isAdmin" :href="adminSettingsShow('general')" class="ml-1 underline">Turn it off in Settings</Link>
    </div>
</template>
