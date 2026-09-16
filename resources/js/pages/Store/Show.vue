<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import ProductCard from '@/components/product/ProductCard.vue';
import {
    toProductCardData,
    type HomeProductItem,
    type HomeSellerItem,
} from '@/types/marketplace';

const props = defineProps<{
    seller: HomeSellerItem | { data: HomeSellerItem };
    products: HomeProductItem[] | { data: HomeProductItem[] };
}>();

const seller = computed<HomeSellerItem>(() =>
    'data' in props.seller ? props.seller.data : props.seller,
);

const productList = computed<HomeProductItem[]>(() =>
    Array.isArray(props.products) ? props.products : props.products.data,
);

const activeTab = ref<'products' | 'rating'>('products');

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
                                    Aktif
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
                                type="button"
                                disabled
                                title="Segera hadir"
                                class="cursor-not-allowed rounded-sm border border-[var(--border-default)] px-4 py-2 text-sm text-[var(--text-faint)]"
                            >
                                Follow
                            </button>
                        </div>
                    </div>

                    <dl
                        class="mt-4 grid grid-cols-3 divide-x divide-[var(--border-soft)] border-t border-[var(--border-soft)] pt-4"
                    >
                        <div class="text-center">
                            <dt class="text-xs text-[var(--text-muted)]">
                                Produk
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
                                class="mt-1 text-lg font-semibold text-[var(--text-muted)]"
                            >
                                —
                            </dd>
                        </div>
                        <div class="text-center">
                            <dt class="text-xs text-[var(--text-muted)]">
                                Pengikut
                            </dt>
                            <dd
                                class="mt-1 text-lg font-semibold text-[var(--text-muted)]"
                            >
                                —
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
                        Produk
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
                            Belum ada produk aktif.
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

                    <p
                        v-else
                        class="py-8 text-center text-sm text-[var(--text-muted)]"
                    >
                        Belum ada rating untuk toko ini.
                    </p>
                </div>
            </section>
        </div>
    </MarketplaceLayout>
</template>
