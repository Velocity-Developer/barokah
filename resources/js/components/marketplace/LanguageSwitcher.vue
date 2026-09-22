<script setup lang="ts">
import { Globe } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';

withDefaults(defineProps<{ variant?: 'bar' | 'compact' }>(), { variant: 'bar' });

const LANGUAGE_NAMES: Record<string, string> = {
    en: 'English',
    ms: 'Bahasa Melayu',
    id: 'Bahasa Indonesia',
    'zh-CN': '中文',
    ta: 'தமிழ்',
    ar: 'العربية',
};

const { getSettingValue, isLoaded } = useSettingsStore();

const enabled = computed(() => getSettingValue<boolean>('localization.gtranslate_enabled', false) === true);
const defaultLanguage = computed(() => getSettingValue<string>('localization.default_language', 'en') || 'en');
const languages = computed<string[]>(() => {
    const list = getSettingValue<string[]>('localization.available_languages', []);
    const all = Array.isArray(list) ? list : [];

    // The site's own language always comes first so visitors can switch back.
    return [defaultLanguage.value, ...all.filter((code) => code !== defaultLanguage.value)];
});
const visible = computed(() => enabled.value && languages.value.length > 1);
const current = ref('');

function languageName(code: string): string {
    return LANGUAGE_NAMES[code] ?? code.toUpperCase();
}

function readCurrent(): string {
    const match = document.cookie.match(/(?:^|;\s*)googtrans=\/[^/]*\/([^;]+)/);

    return match ? decodeURIComponent(match[1]) : defaultLanguage.value;
}

function setCookie(value: string | null): void {
    const expires = value === null ? '; expires=Thu, 01 Jan 1970 00:00:00 GMT' : '';
    const cookie = `googtrans=${value ?? ''}; path=/${expires}`;
    const host = window.location.hostname;

    document.cookie = cookie;
    // Google also writes the cookie on the parent domain; clear/set both.
    if (host.includes('.') && !/^\d+\.\d+\.\d+\.\d+$/.test(host)) {
        document.cookie = `${cookie}; domain=.${host.replace(/^www\./, '')}`;
    }
}

function choose(code: string): void {
    if (code === current.value) return;

    setCookie(code === defaultLanguage.value ? null : `/${defaultLanguage.value}/${code}`);
    // Google Translate applies the cookie on load; a reload keeps Vue's DOM and the translation in sync.
    window.location.reload();
}

/** Load Google's translate element once, hidden; our own switcher drives it via the cookie. */
function loadGoogleTranslate(): void {
    if (document.getElementById('google-translate-script')) return;

    const holder = document.createElement('div');
    holder.id = 'google_translate_element';
    holder.style.display = 'none';
    document.body.appendChild(holder);

    (window as unknown as Record<string, unknown>).googleTranslateElementInit = () => {
        const google = (window as unknown as { google?: { translate?: { TranslateElement: new (options: object, id: string) => unknown } } }).google;

        if (google?.translate) {
            new google.translate.TranslateElement(
                { pageLanguage: defaultLanguage.value, includedLanguages: languages.value.join(','), autoDisplay: false },
                'google_translate_element',
            );
        }
    };

    const script = document.createElement('script');
    script.id = 'google-translate-script';
    script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    script.async = true;
    document.head.appendChild(script);
}

onMounted(() => {
    current.value = readCurrent();
});

// Settings arrive asynchronously; only act once they are known.
watch(
    [isLoaded, visible],
    ([loaded, show]) => {
        if (!loaded) return;

        current.value = readCurrent();

        if (show) {
            loadGoogleTranslate();
        } else if (current.value !== defaultLanguage.value) {
            // Translation was switched off in settings: drop a leftover choice.
            setCookie(null);
            current.value = defaultLanguage.value;
            window.location.reload();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="visible" class="notranslate" translate="no">
        <div v-if="variant === 'bar'" class="flex items-center gap-1.5 after:ml-2.5 after:opacity-50 after:content-['|']">
            <Globe class="size-3.5 opacity-80" aria-hidden="true" />
            <template v-for="(code, index) in languages" :key="code">
                <span v-if="index > 0" aria-hidden="true" class="opacity-50">/</span>
                <button
                    type="button"
                    class="hover:underline"
                    :class="code === current ? 'font-semibold underline underline-offset-2' : 'opacity-90'"
                    :aria-pressed="code === current"
                    :lang="code"
                    @click="choose(code)"
                >
                    {{ languageName(code) }}
                </button>
            </template>
        </div>
        <label v-else class="relative flex items-center">
            <span class="sr-only">Language</span>
            <Globe class="pointer-events-none absolute left-1.5 size-4" aria-hidden="true" />
            <select
                :value="current"
                class="h-8 appearance-none rounded-sm bg-white/10 pr-2 pl-7 text-xs font-medium text-white outline-none"
                @change="choose(($event.target as HTMLSelectElement).value)"
            >
                <option v-for="code in languages" :key="code" :value="code" class="text-black">
                    {{ code.toUpperCase() }}
                </option>
            </select>
        </label>
    </div>
</template>
