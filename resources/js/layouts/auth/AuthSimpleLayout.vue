<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useSettingsStore } from '@/stores/settings';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const { loadSettings } = useSettingsStore();
void loadSettings();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-[#f5f5f5]" style="--brand-primary: #ee4d2d; --brand-primary-hover: #d73211; --text-primary: #1f2937;">
        <header class="bg-[#ee4d2d] px-6 py-4 text-white shadow-sm">
            <div class="mx-auto flex w-full max-w-6xl items-center gap-3">
                <Link :href="home()" class="flex items-center gap-3">
                    <AppLogoIcon class="size-9 fill-current text-white" />
                    <span class="text-2xl font-semibold tracking-tight">Barokah</span>
                </Link>
                <span class="hidden text-xl font-light sm:inline">{{ title }}</span>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 items-center justify-center px-4 py-8 text-gray-800 sm:px-6">
            <div class="grid w-full max-w-5xl overflow-hidden bg-white shadow-sm md:grid-cols-[1fr_420px]">
                <div class="hidden flex-col items-center justify-center bg-[#fff4f0] p-12 text-center md:flex">
                    <div class="mb-6 flex size-24 items-center justify-center rounded-full bg-[#ee4d2d] text-4xl font-bold text-white">B</div>
                    <h2 class="text-3xl font-semibold text-[#ee4d2d]">Barokah Marketplace</h2>
                    <p class="mt-3 max-w-sm text-sm text-gray-500">Belanja kebutuhan pilihan dari toko terpercaya.</p>
                </div>
                <div class="p-6 text-gray-900 [&_label]:text-gray-700 [&_input]:text-gray-900 [&_input]:placeholder:text-gray-400 sm:p-10">
                    <div class="mb-6 space-y-1">
                        <h1 class="text-2xl font-medium text-gray-800">{{ title }}</h1>
                        <p class="text-sm text-gray-500">{{ description }}</p>
                    </div>
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
