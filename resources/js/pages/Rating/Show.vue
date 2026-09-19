<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';

type OrderItem = {
    id: number;
    product_name: string;
    product_slug: string;
    reviewed: boolean;
    rating: number | null;
    review: string | null;
};

const props = defineProps<{ orderItem: OrderItem }>();
const rating = ref(props.orderItem.rating ?? 0);
const review = ref(props.orderItem.review ?? '');
const error = ref('');
const success = ref(props.orderItem.reviewed);
const submitting = ref(false);
const images = ref<File[]>([]);
const video = ref<File | null>(null);

function selectImages(event: Event): void {
    images.value = Array.from((event.target as HTMLInputElement).files ?? []).slice(0, 3);
}

function selectVideo(event: Event): void {
    video.value = (event.target as HTMLInputElement).files?.[0] ?? null;
}

async function submit(): Promise<void> {
    error.value = '';

    if (rating.value < 1 || rating.value > 5) {
        error.value = 'Please choose a rating from 1 to 5.';
        return;
    }

    submitting.value = true;

    try {
        const response = await fetch(`/api/v1/order-items/${props.orderItem.id}/review`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': readXsrfToken(),
            },
            body: (() => {
                const formData = new FormData();
                formData.append('rating', String(rating.value));
                if (review.value) formData.append('review', review.value);
                images.value.forEach((file) => formData.append('images[]', file));
                if (video.value) formData.append('video', video.value);
                return formData;
            })(),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            error.value = body.message ?? Object.values(body.errors ?? {})[0]?.[0] ?? 'Rating could not be saved.';
            return;
        }

        success.value = true;
    } catch {
        error.value = 'Rating could not be saved. Please try again.';
    } finally {
        submitting.value = false;
    }
}

function readXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}
</script>

<template>
    <Head title="Rate product" />
    <MarketplaceLayout>
        <div class="w-full py-8">
            <section class="w-full rounded-xl border border-[var(--border-soft)] bg-white p-6 shadow-sm sm:p-8">
                <h1 class="text-2xl font-bold text-[var(--brand-primary)]">Rate product</h1>
                <p class="mt-2 font-medium">{{ orderItem.product_name }}</p>
                <div v-if="success" class="mt-6 rounded-lg bg-green-50 p-4 text-sm text-green-700">Thank you. This product has been rated.</div>
                <form v-else class="mt-6 space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm font-medium">Your rating</label>
                        <div class="mt-2 flex gap-2">
                            <button v-for="value in 5" :key="value" type="button" :aria-label="`${value} stars`" :class="value <= rating ? 'text-amber-400' : 'text-gray-300'" class="text-3xl" @click="rating = value">★</button>
                        </div>
                    </div>
                    <div>
                        <label for="review" class="block text-sm font-medium">Review (optional)</label>
                        <label class="mt-3 block text-sm font-medium">Media (optional)</label>
                        <p class="mt-1 text-xs text-[var(--text-muted)]">Up to 3 images and 1 video.</p>
                        <input type="file" accept="image/*" multiple class="mt-2 block w-full text-sm" @change="selectImages" />
                        <input type="file" accept="video/*" class="mt-2 block w-full text-sm" @change="selectVideo" />
                        <textarea id="review" v-model="review" rows="5" maxlength="2000" class="mt-2 w-full rounded-md border border-[var(--border-default)] px-3 py-2 text-sm outline-none focus:border-[var(--brand-primary)]" placeholder="Tell us about your experience" />
                    </div>
                    <p v-if="error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
                    <button type="submit" :disabled="submitting" class="rounded-md bg-[var(--brand-primary)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[var(--brand-primary-hover)] disabled:cursor-not-allowed disabled:opacity-50">
                        {{ submitting ? 'Saving…' : 'Submit rating' }}
                    </button>
                </form>
                <Link href="/tracking" class="mt-6 inline-flex text-sm font-semibold text-[var(--brand-primary)] hover:underline">Back to tracking</Link>
            </section>
        </div>
    </MarketplaceLayout>
</template>
