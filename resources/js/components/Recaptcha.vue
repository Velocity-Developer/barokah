<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';

type GrecaptchaV2 = {
    render: (container: HTMLElement, options: { sitekey: string; callback: (token: string) => void; 'expired-callback': () => void; 'error-callback': () => void }) => number;
    reset: (widgetId?: number) => void;
    ready: (callback: () => void) => void;
    execute: (siteKey: string, options: { action: string }) => Promise<string>;
};

declare global {
    interface Window {
        grecaptcha?: GrecaptchaV2;
    }
}

/**
 * Google reCAPTCHA, switched on per place in Settings → Security. It renders
 * nothing while the captcha is off, so pages can include it unconditionally.
 */
const props = defineProps<{
    /** Matches the settings key: 'login' or 'guest_checkout'. */
    context: 'login' | 'guest_checkout';
}>();

const token = defineModel<string>({ default: '' });

const { settings, isLoaded, loadSettings, getSettingValue } = useSettingsStore();

const container = ref<HTMLElement | null>(null);
const active = ref(false);
const version = ref<'v2' | 'v3'>('v2');
const siteKey = ref('');
let widgetId: number | null = null;
let refreshTimer: ReturnType<typeof setInterval> | null = null;

/** v3 tokens expire after two minutes, so fetch a fresh one periodically. */
const V3_REFRESH_MS = 90_000;

function loadScript(src: string): Promise<void> {
    const existing = document.querySelector<HTMLScriptElement>(`script[src="${src}"]`);

    if (existing) {
        return existing.dataset.loaded === 'true'
            ? Promise.resolve()
            : new Promise((resolve, reject) => {
                  existing.addEventListener('load', () => resolve());
                  existing.addEventListener('error', () => reject(new Error('reCAPTCHA failed to load.')));
              });
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => {
            script.dataset.loaded = 'true';
            resolve();
        });
        script.addEventListener('error', () => reject(new Error('reCAPTCHA failed to load.')));
        document.head.appendChild(script);
    });
}

async function requestV3Token(): Promise<void> {
    if (!window.grecaptcha) return;

    try {
        token.value = await window.grecaptcha.execute(siteKey.value, { action: props.context });
    } catch {
        token.value = '';
    }
}

async function setUp(): Promise<void> {
    if (active.value) return;

    const enabled = getSettingValue('security.recaptcha_enabled', false);
    const key = String(settings.value['security.recaptcha_site_key'] ?? '').trim();
    const onHere = getSettingValue(`security.recaptcha_on_${props.context}`, false);

    if (!enabled || !onHere || key === '') return;

    active.value = true;
    siteKey.value = key;
    version.value = settings.value['security.recaptcha_version'] === 'v3' ? 'v3' : 'v2';

    try {
        if (version.value === 'v3') {
            await loadScript(`https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(key)}`);
            window.grecaptcha?.ready(() => {
                void requestV3Token();
                refreshTimer = setInterval(() => void requestV3Token(), V3_REFRESH_MS);
            });

            return;
        }

        await loadScript('https://www.google.com/recaptcha/api.js?render=explicit');
        window.grecaptcha?.ready(() => {
            if (!container.value || !window.grecaptcha) return;

            widgetId = window.grecaptcha.render(container.value, {
                sitekey: key,
                callback: (value: string) => (token.value = value),
                'expired-callback': () => (token.value = ''),
                'error-callback': () => (token.value = ''),
            });
        });
    } catch {
        // Google unreachable: leave the field empty and let the server decide.
        active.value = false;
    }
}

onMounted(async () => {
    await loadSettings();
    await setUp();
});

watch(isLoaded, () => void setUp());

onBeforeUnmount(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});

/** Lets a form ask for a fresh token right before it submits. */
defineExpose({
    refresh: async (): Promise<void> => {
        if (!active.value) return;

        if (version.value === 'v3') {
            await requestV3Token();
        } else if (widgetId !== null) {
            window.grecaptcha?.reset(widgetId);
            token.value = '';
        }
    },
    isActive: active,
});
</script>

<template>
    <div v-if="active" class="grid gap-1">
        <div v-show="version === 'v2'" ref="container" />
        <p v-if="version === 'v3'" class="text-xs text-muted-foreground">
            Protected by reCAPTCHA.
        </p>
    </div>
</template>
