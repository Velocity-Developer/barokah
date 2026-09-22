<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { login } from '@/routes';
import { follow as sellerFollow } from '@/routes/sellers';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import ProductCard from '@/components/product/ProductCard.vue';
import {
    toProductCardData,
    type HomeProductItem,
    type HomeSellerItem,
} from '@/types/marketplace';

type StoreReview = {
    id: number;
    rating: number;
    review: string | null;
    media: { type: 'image' | 'video'; url: string }[];
    created_at: string;
    user?: {
        name: string;
        profile_photo_url?: string | null;
    };
    product?: {
        id: number;
        name: string;
        slug: string;
        image?: string | null;
        average_rating?: number | null;
        ratings_count?: number;
    };
};

const props = defineProps<{
    seller: HomeSellerItem | { data: HomeSellerItem };
    products: HomeProductItem[] | { data: HomeProductItem[] };
    reviews: StoreReview[] | { data: StoreReview[] };
}>();

const seller = computed<HomeSellerItem>(() =>
    'data' in props.seller ? props.seller.data : props.seller,
);

const productList = computed<HomeProductItem[]>(() =>
    Array.isArray(props.products) ? props.products : props.products.data,
);

const reviewList = computed<StoreReview[]>(() =>
    Array.isArray(props.reviews) ? props.reviews : props.reviews.data,
);

const page = usePage();
const isLoggedIn = computed<boolean>(() => Boolean(page.props.auth?.user));
const isFollowed = ref<boolean>(seller.value.is_followed ?? false);
const followersCount = ref<number>(seller.value.followers_count ?? 0);
const followProcessing = ref(false);

watch(seller, (value) => {
    isFollowed.value = value.is_followed ?? false;
    followersCount.value = value.followers_count ?? 0;
});

function toggleFollow(): void {
    if (followProcessing.value) {
        return;
    }

    const previous = { followed: isFollowed.value, count: followersCount.value };
    isFollowed.value = !previous.followed;
    followersCount.value = Math.max(0, previous.count + (previous.followed ? -1 : 1));

    router.post(sellerFollow(seller.value.slug).url, {}, {
        preserveScroll: true,
        onStart: () => {
            followProcessing.value = true;
        },
        onError: () => {
            isFollowed.value = previous.followed;
            followersCount.value = previous.count;
        },
        onFinish: () => {
            followProcessing.value = false;
        },
    });
}

const activeTab = ref<'products' | 'rating'>('products');
const reviewsPerPage = 5;
const visibleReviewCount = ref(reviewsPerPage);
const lightboxMedia = ref<{ type: 'image' | 'video'; url: string } | null>(null);

const reviewFilter = ref<'all' | 'comments' | 'media' | 1 | 2 | 3 | 4 | 5>('all');

const reviewFilters = computed(() =>
    [
        { key: 'all' as const, label: 'All', count: reviewList.value.length },
        { key: 5 as const, label: '5 Star', count: reviewList.value.filter((review) => review.rating === 5).length },
        { key: 4 as const, label: '4 Star', count: reviewList.value.filter((review) => review.rating === 4).length },
        { key: 3 as const, label: '3 Star', count: reviewList.value.filter((review) => review.rating === 3).length },
        { key: 2 as const, label: '2 Star', count: reviewList.value.filter((review) => review.rating === 2).length },
        { key: 1 as const, label: '1 Star', count: reviewList.value.filter((review) => review.rating === 1).length },
        { key: 'comments' as const, label: 'With Comments', count: reviewList.value.filter((review) => Boolean(review.review)).length },
        { key: 'media' as const, label: 'With Media', count: reviewList.value.filter((review) => Boolean(review.media?.length)).length },
    ].filter((filter) => filter.count > 0),
);

function setReviewFilter(filter: typeof reviewFilter.value): void {
    reviewFilter.value = filter;
    visibleReviewCount.value = reviewsPerPage;
}

const filteredReviews = computed<StoreReview[]>(() =>
    reviewList.value.filter((review) => {
        if (reviewFilter.value === 'comments') return Boolean(review.review);
        if (reviewFilter.value === 'media') return Boolean(review.media?.length);
        if (typeof reviewFilter.value === 'number') return review.rating === reviewFilter.value;
        return true;
    }),
);

const visibleReviews = computed<StoreReview[]>(() =>
    filteredReviews.value.slice(0, visibleReviewCount.value),
);

const hasMoreReviews = computed<boolean>(() =>
    visibleReviewCount.value < filteredReviews.value.length,
);

const remainingReviewsCount = computed<number>(() =>
    Math.max(0, filteredReviews.value.length - visibleReviewCount.value),
);

function loadMoreReviews(): void {
    if (!hasMoreReviews.value) {
        return;
    }
    visibleReviewCount.value += reviewsPerPage;
}

function openLightbox(media: { type: 'image' | 'video'; url: string }): void {
    lightboxMedia.value = media;
}

function closeLightbox(): void {
    lightboxMedia.value = null;
}

const location = computed(
    () => seller.value.city || seller.value.state || null,
);

const whatsappLink = computed<string | null>(() => {
    const number = (seller.value.whatsapp || seller.value.phone || '').replace(
        /\D/g,
        '',
    );

    return number ? `https://wa.me/${number}` : null;
});

const productCards = computed(() =>
    productList.value.map((product) => ({
        ...toProductCardData(product),
        location: location.value ?? undefined,
    })),
);
</script>

<template>
    <Head :title="seller.store_name" />
    <MarketplaceLayout>
        <div class="mx-auto w-full max-w-[1200px] px-4 py-4 pb-24 md:pb-6">
            <section
                class="overflow-hidden rounded-sm bg-[var(--bg-surface)] shadow-[var(--shadow-card)]"
            >
                <div
                    v-if="seller.banner_url"
                    class="h-32 w-full bg-cover bg-center md:h-48"
                    :style="{ backgroundImage: `url('${seller.banner_url}')` }"
                    aria-hidden="true"
                />
                <div
                    v-else
                    class="h-32 w-full bg-gradient-to-r from-[var(--brand-primary)] via-[var(--accent-red)] to-[var(--accent-navy)] md:h-40"
                    aria-hidden="true"
                />

                <div class="px-5 pb-4">
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-start md:gap-6"
                    >
                        <img
                            v-if="seller.profile_photo_url"
                            :src="seller.profile_photo_url"
                            :alt="seller.store_name"
                            class="-mt-12 size-20 shrink-0 rounded-full border-4 border-white object-cover md:-mt-14 md:size-24"
                        />
                        <div
                            v-else
                            class="-mt-12 flex size-20 shrink-0 items-center justify-center rounded-full border-4 border-white bg-[var(--accent-navy)] text-2xl font-semibold text-white md:-mt-14 md:size-24"
                            aria-hidden="true"
                        >
                            {{ seller.store_name.charAt(0).toUpperCase() }}
                        </div>

                        <div class="min-w-0 flex-1 pt-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1
                                    class="text-xl font-semibold text-[var(--text-primary)]"
                                >
                                    {{ seller.store_name }}
                                </h1>
                                <span
                                    class="rounded-sm bg-[var(--brand-primary-soft)] px-1.5 py-0.5 text-[11px] font-medium text-[var(--brand-primary)]"
                                >
                                    Active
                                </span>
                            </div>
                            <p
                                class="mt-1 text-sm text-[var(--text-secondary)]"
                            >
                                {{ location ?? 'Marketplace seller' }}
                            </p>
                            <p
                                v-if="seller.description"
                                class="mt-2 line-clamp-2 max-w-2xl text-sm text-[var(--text-muted)]"
                            >
                                {{ seller.description }}
                            </p>
                            <p
                                v-if="seller.store_location"
                                class="mt-1 text-xs text-[var(--text-muted)]"
                            >
                                {{ seller.store_location }}
                            </p>
                        </div>

                        <div class="flex shrink-0 gap-2 pt-1">
                            <a
                                v-if="whatsappLink"
                                :href="whatsappLink"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-sm border border-[var(--brand-primary)] bg-[var(--brand-primary-soft)] px-4 py-2 text-sm font-medium text-[var(--brand-primary)] hover:bg-[var(--brand-primary)] hover:text-white"
                            >
                                Chat
                            </a>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="cursor-not-allowed rounded-sm border border-[var(--border-default)] px-4 py-2 text-sm text-[var(--text-faint)]"
                            >
                                Chat
                            </button>
                            <button
                                v-if="isLoggedIn"
                                type="button"
                                :disabled="followProcessing"
                                :aria-pressed="isFollowed"
                                :class="[
                                    'rounded-sm border px-4 py-2 text-sm font-medium transition disabled:opacity-70',
                                    isFollowed
                                        ? 'border-[var(--border-default)] bg-white text-[var(--text-primary)] hover:bg-[var(--bg-muted)]'
                                        : 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white hover:bg-[var(--brand-primary-hover)]',
                                ]"
                                @click="toggleFollow"
                            >
                                {{ isFollowed ? 'Following' : 'Follow' }}
                            </button>
                            <Link
                                v-else
                                :href="login()"
                                title="Log in to follow"
                                class="rounded-sm border border-[var(--brand-primary)] bg-[var(--brand-primary)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--brand-primary-hover)]"
                            >
                                Follow
                            </Link>
                        </div>
                    </div>

                    <dl
                        class="mt-4 grid grid-cols-3 divide-x divide-[var(--border-soft)] border-t border-[var(--border-soft)] pt-4"
                    >
                        <div class="text-center">
                            <dt class="text-xs text-[var(--text-muted)]">
                                Products
                            </dt>
                            <dd
                                class="mt-1 text-lg font-semibold text-[var(--brand-primary)]"
                            >
                                {{ productCards.length }}
                            </dd>
                        </div>
                        <div class="text-center">
                            <dt class="text-xs text-[var(--text-muted)]">
                                Rating
                            </dt>
                            <dd
                                class="mt-1 text-lg font-semibold text-[var(--brand-primary)]"
                            >
                                {{ seller.average_rating !== null && seller.average_rating !== undefined ? Number(seller.average_rating).toFixed(1) : '—' }}
                                <span v-if="seller.ratings_count">({{ seller.ratings_count }})</span>
                            </dd>
                        </div>
                        <div class="text-center">
                            <dt class="text-xs text-[var(--text-muted)]">
                                Followers
                            </dt>
                            <dd
                                class="mt-1 text-lg font-semibold text-[var(--brand-primary)]"
                            >
                                {{ followersCount }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                class="mt-4 rounded-sm bg-[var(--bg-surface)] shadow-[var(--shadow-card)]"
            >
                <div
                    class="flex gap-6 border-b border-[var(--border-soft)] px-5"
                >
                    <button
                        type="button"
                        class="border-b-2 py-3 text-sm font-medium transition"
                        :class="
                            activeTab === 'products'
                                ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]'
                                : 'border-transparent text-[var(--text-secondary)]'
                        "
                        @click="activeTab = 'products'"
                    >
                        Products
                    </button>
                    <button
                        type="button"
                        class="border-b-2 py-3 text-sm font-medium transition"
                        :class="
                            activeTab === 'rating'
                                ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]'
                                : 'border-transparent text-[var(--text-secondary)]'
                        "
                        @click="activeTab = 'rating'"
                    >
                        Rating
                    </button>
                </div>

                <div class="p-4">
                    <template v-if="activeTab === 'products'">
                        <p
                            v-if="productCards.length === 0"
                            class="py-8 text-center text-sm text-[var(--text-muted)]"
                        >
                            No active products yet.
                        </p>
                        <div
                            v-else
                            class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
                        >
                            <ProductCard
                                v-for="card in productCards"
                                :key="card.id"
                                :product="card"
                            />
                        </div>
                    </template>

                    <template v-else>
                        <div v-if="reviewList.length">
                            <div class="flex flex-wrap gap-2 border-b border-[var(--border-soft)] pb-4 text-xs">
                                <button
                                    v-for="filter in reviewFilters"
                                    :key="filter.key"
                                    type="button"
                                    :class="
                                        reviewFilter === filter.key
                                            ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]'
                                            : 'border-[var(--border-default)] text-[var(--text-secondary)]'
                                    "
                                    class="border bg-white px-3 py-2"
                                    @click="setReviewFilter(filter.key)"
                                >
                                    {{ filter.label }} ({{ filter.count }})
                                </button>
                            </div>
                            <div class="mt-2">
                                <template v-if="filteredReviews.length">
                                    <div class="divide-y divide-[var(--border-soft)]">
                                <article
                                    v-for="review in visibleReviews"
                                    :key="review.id"
                                    class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-3 py-4 first:pt-0 last:pb-0"
                                >
                                    <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-orange-100 text-sm font-semibold text-orange-600">
                                        <img
                                            v-if="review.user?.profile_photo_url"
                                            :src="review.user.profile_photo_url"
                                            alt="Reviewer"
                                            class="size-full object-cover"
                                        />
                                        <span v-else>{{ (review.user?.name ?? 'Buyer').charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-[var(--text-primary)]">{{ review.user?.name ?? 'Buyer' }}</p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm tracking-wide text-orange-500">{{ '★'.repeat(review.rating) }}</span>
                                            <span class="text-xs text-[var(--text-muted)]">{{ new Date(review.created_at).toLocaleDateString('en-GB') }}</span>
                                        </div>
                                        <Link
                                            v-if="review.product"
                                            :href="`/products/${review.product.slug}`"
                                            class="mt-3 flex max-w-md gap-3 rounded-sm bg-[var(--bg-muted)] p-2 transition hover:bg-[var(--brand-primary-soft)]"
                                        >
                                            <img
                                                v-if="review.product.image"
                                                :src="review.product.image"
                                                :alt="review.product.name"
                                                class="size-14 shrink-0 rounded-sm object-cover"
                                            />
                                            <div class="min-w-0 self-center">
                                                <p class="truncate text-sm font-medium text-[var(--text-primary)]">{{ review.product.name }}</p>
                                                <p class="mt-0.5 text-xs text-[var(--brand-primary)]">
                                                    Rating {{ review.product.average_rating !== null && review.product.average_rating !== undefined ? Number(review.product.average_rating).toFixed(1) : '—' }}
                                                    ({{ review.product.ratings_count ?? 0 }} Rating)
                                                </p>
                                            </div>
                                        </Link>
                                        <p v-if="review.review" class="mt-2 text-sm leading-relaxed text-[var(--text-secondary)]">{{ review.review }}</p>
                                        <div v-if="review.media?.length" class="mt-3 flex flex-wrap gap-2">
                                            <template v-for="media in review.media" :key="media.url">
                                                <button v-if="media.type === 'image'" type="button" class="block" @click="openLightbox(media)">
                                                    <img
                                                        :src="media.url"
                                                        alt="Review media"
                                                        class="size-16 cursor-zoom-in rounded-sm object-cover"
                                                    />
                                                </button>
                                                <button v-else type="button" class="block" @click="openLightbox(media)">
                                                    <video
                                                        :src="media.url"
                                                        class="h-16 w-24 cursor-zoom-in rounded-sm object-cover"
                                                    />
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </article>
                                    </div>
                                    <div v-if="hasMoreReviews" class="mt-4 flex justify-center">
                                        <button
                                            type="button"
                                            class="inline-flex h-10 items-center justify-center rounded-sm border border-[var(--brand-primary)] bg-white px-5 text-sm font-medium text-[var(--brand-primary)] transition hover:bg-[var(--brand-primary-soft)]"
                                            @click="loadMoreReviews"
                                        >
                                            Load more
                                            <span class="ml-1 text-xs text-[var(--text-muted)]">({{ remainingReviewsCount }} remaining)</span>
                                        </button>
                                    </div>
                                </template>
                                <p
                                    v-else
                                    class="py-6 text-sm text-[var(--text-muted)]"
                                >
                                    No reviews match this filter.
                                </p>
                            </div>
                        </div>
                        <p
                            v-else
                            class="py-8 text-center text-sm text-[var(--text-muted)]"
                        >
                            This store has no ratings yet.
                        </p>
                    </template>
                </div>
            </section>
        </div>

        <div
            v-if="lightboxMedia"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            @click.self="closeLightbox"
        >
            <button
                type="button"
                aria-label="Close media preview"
                class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white/90 text-2xl text-gray-800"
                @click="closeLightbox"
            >
                ×
            </button>
            <img
                v-if="lightboxMedia.type === 'image'"
                :src="lightboxMedia.url"
                alt="Review media preview"
                class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain"
            />
            <video
                v-else
                :src="lightboxMedia.url"
                controls
                autoplay
                class="max-h-[90vh] max-w-[90vw] rounded-lg"
            />
        </div>
    </MarketplaceLayout>
</template>
