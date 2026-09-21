<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { show as sellerShow } from '@/routes/sellers';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import ProductCard from '@/components/product/ProductCard.vue';
import {
    toProductCardData,
    type HomeProductItem,
} from '@/types/marketplace';
import { useCheckoutStore } from '@/stores/checkout';
import { useCartStore } from '@/stores/cart';
import { useSettingsStore } from '@/stores/settings';

type DetailImage = {
    id: number;
    url: string;
    sort_order: number;
    is_primary: boolean;
};

type ProductReview = {
    id: number;
    rating: number;
    review: string | null;
    media?: { type: 'image' | 'video'; url: string }[];
    created_at: string;
    user?: { name: string; profile_photo_url?: string | null };
};

type DetailProduct = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: string | number;
    normal_price?: string | number;
    effective_price?: string | number;
    flash_sale_active?: boolean;
    flash_sale?: { ends_at?: string; remaining_quantity?: number } | null;
    stock: number;
    weight_grams?: number;
    status: string;
    seller: { store_name: string; slug: string } | null;
    category: { name: string; slug: string } | null;
    images: DetailImage[] | { data: DetailImage[] };
    primary_image: string | null;
    average_rating?: number | null;
    ratings_count?: number;
    reviews?: ProductReview[] | { data: ProductReview[] };
};

const props = defineProps<{
    product: DetailProduct | { data: DetailProduct };
    sellerProducts?: DetailProduct[] | { data: DetailProduct[] };
}>();

const { formatAmount } = useSettingsStore();
const { startBuy } = useCheckoutStore();
const { add } = useCartStore();

/**
 * A single JsonResource serializes through Inertia as `{ data: {...} }`;
 * unwrap it so the template reads the product directly (same convention as
 * the collection pages).
 */
const product = computed<DetailProduct>(() => {
    const raw = props.product as DetailProduct | { data: DetailProduct };
    if (raw && 'data' in raw && raw.data && typeof raw.data === 'object') {
        return raw.data;
    }
    return raw as DetailProduct;
});

const activeImage = ref(product.value.primary_image);
const quantity = ref(1);

function unwrapImages(images: DetailProduct['images']): DetailImage[] {
    return Array.isArray(images) ? images : (images?.data ?? []);
}

const gallery = computed(() => unwrapImages(product.value.images));

const reviews = computed<ProductReview[]>(() => {
    const raw = product.value.reviews;
    return raw ? (Array.isArray(raw) ? raw : (raw.data ?? [])) : [];
});

const reviewFilter = ref<'all' | 'comments' | 'media' | 1 | 2 | 3 | 4 | 5>('all');
const reviewsPerPage = 5;
const visibleReviewCount = ref(reviewsPerPage);
const lightboxMedia = ref<{ type: 'image' | 'video'; url: string } | null>(null);

function openLightbox(media: { type: 'image' | 'video'; url: string }): void {
    lightboxMedia.value = media;
}

function closeLightbox(): void {
    lightboxMedia.value = null;
}

const reviewFilters = computed(() => [
    { key: 'all' as const, label: 'All', count: reviews.value.length },
    { key: 5 as const, label: '5 Star', count: reviews.value.filter((review) => review.rating === 5).length },
    { key: 4 as const, label: '4 Star', count: reviews.value.filter((review) => review.rating === 4).length },
    { key: 3 as const, label: '3 Star', count: reviews.value.filter((review) => review.rating === 3).length },
    { key: 2 as const, label: '2 Star', count: reviews.value.filter((review) => review.rating === 2).length },
    { key: 1 as const, label: '1 Star', count: reviews.value.filter((review) => review.rating === 1).length },
    { key: 'comments' as const, label: 'With Comments', count: reviews.value.filter((review) => Boolean(review.review)).length },
    { key: 'media' as const, label: 'With Media', count: reviews.value.filter((review) => Boolean(review.media?.length)).length },
].filter((filter) => filter.count > 0));

function setReviewFilter(filter: typeof reviewFilter.value): void {
    reviewFilter.value = filter;
    visibleReviewCount.value = reviewsPerPage;
}

const filteredReviews = computed(() => reviews.value.filter((review) => {
    if (reviewFilter.value === 'comments') return Boolean(review.review);
    if (reviewFilter.value === 'media') return Boolean(review.media?.length);
    if (typeof reviewFilter.value === 'number') return review.rating === reviewFilter.value;
    return true;
}));

const visibleReviews = computed<ProductReview[]>(() =>
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

const otherProducts = computed<DetailProduct[]>(() => {
    const raw = props.sellerProducts;

    if (!raw) {
        return [];
    }

    return Array.isArray(raw) ? raw : (raw.data ?? []);
});

const otherProductCards = computed(() =>
    otherProducts.value.map((item) =>
        toProductCardData(item as unknown as HomeProductItem),
    ),
);

const isOutOfStock = computed(() => product.value.stock <= 0);

const weightLabel = computed(() =>
    product.value.weight_grams != null
        ? `${product.value.weight_grams} g`
        : null,
);

function selectImage(url: string): void {
    activeImage.value = url;
}

function increment(): void {
    if (quantity.value < product.value.stock) {
        quantity.value += 1;
    }
}

function decrement(): void {
    if (quantity.value > 1) {
        quantity.value -= 1;
    }
}

function addToCart(): void {
    add({
        productId: product.value.id,
        slug: product.value.slug,
        name: product.value.name,
        price: Number(product.value.effective_price ?? product.value.price),
        image: product.value.primary_image,
        stock: product.value.stock,
    }, quantity.value);
}
</script>

<template>
    <Head :title="product.name" />

    <MarketplaceLayout>
        <div class="mx-auto w-full max-w-[1200px] px-4 py-6 pb-24 md:pb-6">
            <!-- Breadcrumb -->
            <nav class="mb-4 text-xs text-[var(--text-muted)]">
                <Link href="/products" class="hover:underline">Products</Link>
                <span v-if="product.seller">
                    <span class="mx-1">/</span>
                    <Link
                        :href="sellerShow.url(product.seller.slug)"
                        class="hover:underline"
                    >
                        {{ product.seller.store_name }}
                    </Link>
                </span>
                <span v-if="product.category">
                    <span class="mx-1">/</span>
                    <Link
                        :href="`/products?category=${product.category.slug}`"
                        class="hover:underline"
                    >
                        {{ product.category.name }}
                    </Link>
                </span>
                <span class="mx-1">/</span>
                <span class="text-[var(--text-primary)]">
                    {{ product.name }}
                </span>
            </nav>

            <div
                class="grid gap-6 rounded-sm border border-[var(--border-default)] bg-white p-4 md:grid-cols-2 md:gap-10 md:p-6"
            >
                <!-- Gallery -->
                <div class="md:sticky md:top-24 md:self-start">
                    <div
                        class="aspect-square overflow-hidden rounded-sm border border-[var(--border-default)] bg-[var(--bg-muted)]"
                    >
                        <img
                            v-if="activeImage"
                            :src="activeImage"
                            :alt="product.name"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-sm text-[var(--text-muted)]"
                        >
                            No image available
                        </div>
                    </div>
                    <div
                        v-if="gallery.length > 1"
                        class="mt-3 flex gap-2 overflow-x-auto pb-1"
                    >
                        <button
                            v-for="image in gallery"
                            :key="image.id"
                            type="button"
                            :aria-label="`View image ${image.sort_order + 1}`"
                            :class="[
                                'h-16 w-16 shrink-0 overflow-hidden rounded-sm border-2',
                                activeImage === image.url
                                    ? 'border-[var(--brand-primary)]'
                                    : 'border-transparent hover:border-[var(--border-default)]',
                            ]"
                            @click="selectImage(image.url)"
                        >
                            <img
                                :src="image.url"
                                :alt="product.name"
                                class="h-full w-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="flex flex-col">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1
                                class="text-xl font-semibold text-[var(--text-primary)] md:text-2xl"
                            >
                                {{ product.name }}
                            </h1>
                            <p
                                v-if="product.seller"
                                class="mt-1.5 text-sm text-[var(--text-secondary)]"
                            >
                                Sold by
                                <Link
                                    :href="sellerShow.url(product.seller.slug)"
                                    class="font-medium text-[var(--brand-primary)] hover:underline"
                                >
                                    {{ product.seller.store_name }}
                                </Link>
                            </p>
                        </div>
                        <span
                            :class="[
                                'shrink-0 rounded-sm px-2 py-1 text-[11px] font-semibold',
                                isOutOfStock
                                    ? 'bg-[var(--accent-red)] text-white'
                                    : 'bg-[var(--accent-cyan)]/10 text-[var(--accent-cyan)]',
                            ]"
                        >
                            {{ isOutOfStock ? 'Out of stock' : 'In stock' }}
                        </span>
                    </div>

                    <div
                        v-if="product.ratings_count"
                        class="mt-4 flex items-center gap-2 text-sm"
                    >
                        <span class="font-semibold text-amber-500">★</span>
                        <span class="font-semibold text-[var(--text-primary)]">
                            {{ Number(product.average_rating ?? 0).toFixed(1) }}/5
                        </span>
                        <span class="text-[var(--text-muted)]">
                            from {{ product.ratings_count }} reviews
                        </span>
                    </div>

                    <!-- Price panel -->
                    <div
                        class="mt-5 rounded-sm bg-[var(--brand-primary-soft)] p-4"
                    >
                        <span
                            v-if="product.flash_sale_active"
                            class="inline-block rounded-sm bg-[var(--accent-red)] px-2 py-1 text-xs font-bold text-white"
                        >
                            FLASH SALE
                        </span>
                        <p
                            v-if="product.flash_sale_active"
                            class="mt-2 text-sm text-[var(--text-muted)] line-through"
                        >
                            {{ formatAmount(Number(product.normal_price ?? product.price)) }}
                        </p>
                        <p
                            class="text-3xl font-semibold tracking-tight text-[var(--brand-primary)]"
                        >
                            {{ formatAmount(Number(product.effective_price ?? product.price)) }}
                        </p>
                        <p
                            v-if="product.flash_sale_active && product.flash_sale?.remaining_quantity !== undefined"
                            class="mt-1 text-xs font-medium text-[var(--accent-red)]"
                        >
                            {{ product.flash_sale.remaining_quantity }} promo quota remaining
                        </p>
                        <p
                            v-if="!isOutOfStock"
                            class="mt-1 text-sm text-[var(--text-secondary)]"
                        >
                            {{ product.stock }} available
                        </p>
                    </div>

                    <!-- Quantity -->
                    <div class="mt-6">
                        <p
                            class="mb-2 text-sm font-medium text-[var(--text-primary)]"
                        >
                            Quantity
                        </p>
                        <div
                            class="inline-flex h-11 items-center overflow-hidden rounded-sm border border-[var(--border-default)]"
                        >
                            <button
                                type="button"
                                :disabled="quantity <= 1 || isOutOfStock"
                                class="flex h-full w-10 items-center justify-center text-lg text-[var(--text-secondary)] disabled:opacity-40"
                                aria-label="Decrease quantity"
                                @click="decrement"
                            >
                                −
                            </button>
                            <span
                                class="w-12 text-center text-sm font-semibold text-[var(--text-primary)]"
                            >
                                {{ quantity }}
                            </span>
                            <button
                                type="button"
                                :disabled="
                                    quantity >= product.stock || isOutOfStock
                                "
                                class="flex h-full w-10 items-center justify-center text-lg text-[var(--text-secondary)] disabled:opacity-40"
                                aria-label="Increase quantity"
                                @click="increment"
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            :disabled="isOutOfStock"
                            class="flex h-12 flex-1 items-center justify-center gap-2 rounded-sm border-2 border-[var(--brand-primary)] bg-[var(--brand-primary-soft)] px-6 font-semibold text-[var(--brand-primary)] transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
                            @click="addToCart"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-5 w-5"
                                aria-hidden="true"
                            >
                                <circle cx="8" cy="21" r="1" />
                                <circle cx="19" cy="21" r="1" />
                                <path
                                    d="M2.05 2.05h2l2.66 12.54a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57L21 7H6"
                                />
                            </svg>
                            Add to Cart
                        </button>
                        <Link
                            :href="`/checkout/${product.slug}?quantity=${quantity}`"
                            :class="[
                                'flex h-12 flex-1 items-center justify-center rounded-sm bg-[var(--brand-primary)] px-6 font-semibold text-white transition hover:bg-[var(--brand-primary-hover)]',
                                isOutOfStock
                                    ? 'pointer-events-none opacity-50'
                                    : '',
                            ]"
                            @click="
                                startBuy(product.id, product.slug, quantity)
                            "
                        >
                            Buy Now
                        </Link>
                    </div>
                    <p class="mt-2 text-xs text-[var(--text-muted)]">
                        Cart items are saved in this browser and remain available after refresh.
                    </p>
                </div>
            </div>

            <!-- Store card -->
            <section
                v-if="product.seller"
                class="mt-4 flex flex-col gap-3 rounded-sm border border-[var(--border-default)] bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[var(--accent-navy)] text-lg font-semibold text-white"
                        aria-hidden="true"
                    >
                        {{ product.seller.store_name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <h2
                            class="text-sm font-semibold text-[var(--text-primary)]"
                        >
                            {{ product.seller.store_name }}
                        </h2>
                        <p class="text-xs text-[var(--text-muted)]">
                            Lihat semua produk toko ini
                        </p>
                    </div>
                </div>
                <Link
                    :href="sellerShow.url(product.seller.slug)"
                    class="inline-flex h-10 items-center justify-center rounded-sm border border-[var(--brand-primary)] px-4 text-sm font-medium text-[var(--brand-primary)] hover:bg-[var(--brand-primary-soft)]"
                >
                    Kunjungi toko
                </Link>
            </section>

            <!-- Specification card -->
            <section
                class="mt-4 rounded-sm border border-[var(--border-default)] bg-white"
            >
                <h2
                    class="border-b border-[var(--border-soft)] px-4 py-3 text-sm font-semibold text-[var(--text-primary)]"
                >
                    Product Specification
                </h2>
                <dl class="divide-y divide-[var(--border-soft)] text-sm">
                    <div class="grid grid-cols-3 gap-3 px-4 py-3">
                        <dt class="text-[var(--text-muted)]">Category</dt>
                        <dd class="col-span-2 text-[var(--text-primary)]">
                            {{ product.category?.name ?? '—' }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 px-4 py-3">
                        <dt class="text-[var(--text-muted)]">Weight</dt>
                        <dd class="col-span-2 text-[var(--text-primary)]">
                            {{ weightLabel ?? '—' }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 px-4 py-3">
                        <dt class="text-[var(--text-muted)]">Stock</dt>
                        <dd class="col-span-2 text-[var(--text-primary)]">
                            {{
                                isOutOfStock
                                    ? 'Sold out'
                                    : `${product.stock} pcs`
                            }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-3 px-4 py-3">
                        <dt class="text-[var(--text-muted)]">Store</dt>
                        <dd class="col-span-2 text-[var(--text-primary)]">
                            {{ product.seller?.store_name ?? '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="border-t border-[var(--border-soft)]">
                    <h3
                        class="px-4 py-3 text-sm font-semibold text-[var(--text-primary)]"
                    >
                        Product Description
                    </h3>
                    <p
                        class="px-4 pb-4 text-sm leading-relaxed whitespace-pre-line text-[var(--text-secondary)]"
                    >
                        {{ product.description || '—' }}
                    </p>
                </div>
            </section>

            <!-- Rating card -->
            <section class="mt-4 rounded-sm border border-[var(--border-default)] bg-white">
                <div class="border-b border-[var(--border-soft)] px-4 py-3">
                    <h2 class="text-sm font-semibold text-[var(--text-primary)]">Product Ratings & Reviews</h2>
                </div>
                <div class="px-4 py-3">
                    <p class="text-sm text-[var(--text-muted)]">{{ Number(product.average_rating ?? 0).toFixed(1) }} out of 5 · {{ product.ratings_count ?? 0 }} reviews</p>
                </div>
                <div class="flex flex-wrap gap-2 border-b border-[var(--border-soft)] px-4 py-4 text-xs">
                    <button v-for="filter in reviewFilters" :key="filter.key" type="button" :class="reviewFilter === filter.key ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]' : 'border-[var(--border-default)] text-[var(--text-secondary)]'" class="border bg-white px-3 py-2" @click="setReviewFilter(filter.key)">{{ filter.label }} ({{ filter.count }})</button>
                </div>
                <div class="mt-2 px-4 py-4">
                    <template v-if="filteredReviews.length">
                        <div class="divide-y divide-[var(--border-soft)]">
                            <article v-for="review in visibleReviews" :key="review.id" class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-3 py-4 first:pt-0 last:pb-0">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-orange-100 text-sm font-semibold text-orange-600">
                                    <img v-if="review.user?.profile_photo_url" :src="review.user.profile_photo_url" alt="Reviewer" class="h-full w-full object-cover" />
                                    <span v-else>{{ (review.user?.name ?? 'Buyer').charAt(0).toUpperCase() }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-[var(--text-primary)]">{{ review.user?.name ?? 'Buyer' }}</p>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm tracking-wide text-orange-500">{{ '★'.repeat(review.rating) }}</span>
                                        <span class="text-xs text-[var(--text-muted)]">{{ new Date(review.created_at).toLocaleDateString('en-GB') }}</span>
                                    </div>
                                    <p v-if="review.review" class="mt-2 text-sm leading-relaxed text-[var(--text-secondary)]">{{ review.review }}</p>
                                    <div v-if="review.media?.length" class="mt-3 flex flex-wrap gap-2">
                                    <template v-for="media in review.media" :key="media.url">
                                        <button v-if="media.type === 'image'" type="button" class="block" @click="openLightbox(media)">
                                            <img :src="media.url" alt="Review media" class="h-20 w-20 cursor-zoom-in rounded-md object-cover" />
                                        </button>
                                        <button v-else type="button" class="block" @click="openLightbox(media)">
                                            <video :src="media.url" class="h-20 w-32 cursor-zoom-in rounded-md object-cover" />
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
                    <p v-else class="py-6 text-sm text-[var(--text-muted)]">No reviews match this filter.</p>
                </div>
            </section>

            <!-- Other products from this store -->
            <section
                v-if="otherProductCards.length > 0"
                class="mt-4 rounded-sm border border-[var(--border-default)] bg-white"
            >
                <div
                    class="flex items-center justify-between border-b border-[var(--border-soft)] px-4 py-3"
                >
                    <h2 class="text-sm font-semibold text-[var(--text-primary)]">
                        Other products from this store
                    </h2>
                    <Link
                        v-if="product.seller"
                        :href="sellerShow.url(product.seller.slug)"
                        class="text-xs font-medium text-[var(--brand-primary)] hover:underline"
                    >
                        View all
                    </Link>
                </div>
                <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-3 md:grid-cols-6">
                    <ProductCard
                        v-for="card in otherProductCards"
                        :key="card.id"
                        :product="card"
                    />
                </div>
            </section>
        </div>

        <div v-if="lightboxMedia" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click.self="closeLightbox">
            <button type="button" aria-label="Close media preview" class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white/90 text-2xl text-gray-800" @click="closeLightbox">×</button>
            <img v-if="lightboxMedia.type === 'image'" :src="lightboxMedia.url" alt="Review media preview" class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain" />
            <video v-else :src="lightboxMedia.url" controls autoplay class="max-h-[90vh] max-w-[90vw] rounded-lg" />
        </div>
    </MarketplaceLayout>
</template>
