<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { useSettingsStore } from '@/stores/settings';

defineProps<{
    title?: string;
    description?: string;
}>();

const { getSettingValue } = useSettingsStore();
const page = usePage();
const siteName = computed(() => String(page.props.name || 'Barokah'));
const logoUrl = computed(() => getSettingValue<string>('branding.logo_url', ''));
const faviconUrl = computed(() => getSettingValue<string>('branding.favicon_url', ''));
const logoTagline = computed(() => getSettingValue<string>('branding.logo_tagline', ''));
</script>

<template>
    <div class="flex min-h-svh flex-col bg-[var(--bg-page)]" style="--text-primary: #1f2937;">
        <header class="mp-header-bg px-6 py-4 text-white shadow-sm">
            <div class="mx-auto flex w-full max-w-6xl items-center gap-3">
                <Link :href="home()" class="flex items-center gap-3" :aria-label="siteName">
                    <span v-if="logoUrl" class="flex flex-col items-center rounded-md bg-white px-2 py-1">
                        <img :src="logoUrl" :alt="siteName" class="h-9 w-auto max-w-40 object-contain" />
                        <span
                            v-if="logoTagline"
                            class="text-[11px] leading-tight font-bold"
                            style="color: var(--brand-primary)"
                        >
                            {{ logoTagline }}
                        </span>
                    </span>
                    <template v-else>
                        <AppLogoIcon class="size-9 fill-current text-white" />
                        <span class="text-2xl font-semibold tracking-tight">{{ siteName }}</span>
                    </template>
                </Link>
                <span class="hidden text-xl font-light sm:inline">{{ title }}</span>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-1 items-center justify-center px-4 py-8 text-gray-800 sm:px-6">
            <div class="grid w-full max-w-5xl overflow-hidden bg-white shadow-sm md:grid-cols-[1fr_420px]">
                <div class="hidden flex-col items-center justify-center bg-[var(--brand-primary-soft)] p-12 text-center md:flex">
                    <div v-if="faviconUrl" class="mb-6 flex size-24 items-center justify-center rounded-full bg-white p-4 shadow-sm">
                        <img :src="faviconUrl" alt="" class="size-full object-contain" />
                    </div>
                    <div v-else class="mb-6 flex size-24 items-center justify-center rounded-full bg-[var(--brand-primary)] text-4xl font-bold text-white">{{ siteName.charAt(0).toUpperCase() }}</div>
                    <h2 class="text-3xl font-semibold text-[var(--brand-primary)] font-[family-name:var(--font-display)]">{{ siteName }} Marketplace</h2>
                    <p class="mt-3 max-w-sm text-sm text-gray-500">Shop everyday essentials from trusted stores.</p>
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
