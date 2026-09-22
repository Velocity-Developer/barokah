<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { Camera, ImageIcon } from '@lucide/vue';
import InputError from '@/components/InputError.vue';

/**
 * Store banner + profile photo picker, previewed the way the storefront shows
 * them. The owner's profile photo / banner are used while the store has none.
 */
const props = defineProps<{
    storeName: string;
    photoUrl?: string | null;
    bannerUrl?: string | null;
    fallbackPhotoUrl?: string | null;
    fallbackBannerUrl?: string | null;
    errors?: Record<string, string | undefined>;
}>();

const photo = defineModel<File | null>('photo', { default: null });
const removePhoto = defineModel<boolean>('removePhoto', { default: false });
const banner = defineModel<File | null>('banner', { default: null });
const removeBanner = defineModel<boolean>('removeBanner', { default: false });

const photoPreview = ref<string | null>(null);
const bannerPreview = ref<string | null>(null);

function syncPreview(preview: Ref<string | null>, file: File | null): void {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = file ? URL.createObjectURL(file) : null;
}

watch(photo, (file) => syncPreview(photoPreview, file), { immediate: true });
watch(banner, (file) => syncPreview(bannerPreview, file), { immediate: true });

onBeforeUnmount(() => {
    syncPreview(photoPreview, null);
    syncPreview(bannerPreview, null);
});

// Own media after pending changes; null means the fallback (or placeholder) shows.
const ownPhoto = computed(() => photoPreview.value ?? (removePhoto.value ? null : (props.photoUrl ?? null)));
const ownBanner = computed(() => bannerPreview.value ?? (removeBanner.value ? null : (props.bannerUrl ?? null)));
const shownPhoto = computed(() => ownPhoto.value ?? props.fallbackPhotoUrl ?? null);
const shownBanner = computed(() => ownBanner.value ?? props.fallbackBannerUrl ?? null);

watch(photo, (file) => file && (removePhoto.value = false));
watch(banner, (file) => file && (removeBanner.value = false));

function pick(event: Event, kind: 'photo' | 'banner'): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (kind === 'photo') {
        photo.value = file;
    } else {
        banner.value = file;
    }
}

function sourceNote(own: string | null, fallback?: string | null): string {
    if (own) {
        return '';
    }

    return fallback ? "Using the owner's profile image until the store has its own." : '';
}
</script>

<template>
    <div class="grid content-start gap-3">
        <div class="relative">
            <div
                class="h-28 overflow-hidden rounded-lg border bg-cover bg-center sm:h-36"
                :class="shownBanner ? '' : 'bg-gradient-to-r from-[var(--brand-primary,#ee4d2d)] to-[var(--accent-navy,#113366)]'"
                :style="shownBanner ? { backgroundImage: `url('${shownBanner}')` } : undefined"
                role="img"
                :aria-label="`${storeName} banner preview`"
            />
            <div class="absolute -bottom-8 left-4">
                <img v-if="shownPhoto" :src="shownPhoto" :alt="storeName" class="size-20 rounded-full border-4 border-card bg-card object-cover" />
                <span v-else class="flex size-20 items-center justify-center rounded-full border-4 border-card bg-[var(--accent-navy,#113366)] text-2xl font-semibold text-white" aria-hidden="true">
                    {{ (storeName || '?').charAt(0).toUpperCase() }}
                </span>
            </div>
        </div>

        <div class="grid gap-4 pt-8 sm:grid-cols-2">
            <div class="grid content-start gap-1.5">
                <p class="text-sm font-medium">Store photo</p>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <Camera class="size-4" aria-hidden="true" /> {{ ownPhoto ? 'Change photo' : 'Upload photo' }}
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event, 'photo')" />
                    </label>
                    <button v-if="photo" type="button" class="h-8 rounded-md border px-3 text-sm font-medium hover:bg-muted" @click="photo = null">Undo</button>
                    <button v-else-if="photoUrl" type="button" class="h-8 rounded-md border px-3 text-sm font-medium hover:bg-muted" @click="removePhoto = !removePhoto">
                        {{ removePhoto ? 'Keep photo' : 'Remove' }}
                    </button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Square, JPG/PNG/WebP · max 2 MB<template v-if="removePhoto"> · removed on save</template>
                </p>
                <p v-if="sourceNote(ownPhoto, fallbackPhotoUrl)" class="text-xs text-muted-foreground">{{ sourceNote(ownPhoto, fallbackPhotoUrl) }}</p>
                <InputError :message="errors?.profile_photo ?? errors?.remove_profile_photo" />
            </div>

            <div class="grid content-start gap-1.5">
                <p class="text-sm font-medium">Store banner</p>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <ImageIcon class="size-4" aria-hidden="true" /> {{ ownBanner ? 'Change banner' : 'Upload banner' }}
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event, 'banner')" />
                    </label>
                    <button v-if="banner" type="button" class="h-8 rounded-md border px-3 text-sm font-medium hover:bg-muted" @click="banner = null">Undo</button>
                    <button v-else-if="bannerUrl" type="button" class="h-8 rounded-md border px-3 text-sm font-medium hover:bg-muted" @click="removeBanner = !removeBanner">
                        {{ removeBanner ? 'Keep banner' : 'Remove' }}
                    </button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Wide image, about 1200 × 300 px · max 4 MB<template v-if="removeBanner"> · removed on save</template>
                </p>
                <p v-if="sourceNote(ownBanner, fallbackBannerUrl)" class="text-xs text-muted-foreground">{{ sourceNote(ownBanner, fallbackBannerUrl) }}</p>
                <InputError :message="errors?.banner ?? errors?.remove_banner" />
            </div>
        </div>
    </div>
</template>
