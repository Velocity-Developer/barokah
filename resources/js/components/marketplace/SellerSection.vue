<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Star } from '@lucide/vue';
import { show as sellerShow } from '@/routes/sellers';
import type { HomeSellerItem } from '@/types/marketplace';

defineProps<{
    sellers: HomeSellerItem[];
}>();

function location(seller: HomeSellerItem): string {
    return seller.city || seller.state || 'Marketplace seller';
}

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}
</script>

<template>
    <section
        aria-label="Featured sellers"
        class="mt-5 rounded-sm bg-white p-4 shadow-[var(--shadow-card)]"
    >
        <div
            class="flex min-h-[56px] items-center border-b border-[var(--border-soft)] pb-3"
        >
            <div>
                <h2 class="text-base font-semibold text-[var(--text-primary)]">
                    Featured Sellers
                </h2>
                <p class="text-xs text-[var(--text-muted)]">
                    Popular stores on the marketplace
                </p>
            </div>
        </div>

        <p v-if="sellers.length === 0" class="mt-3 text-sm text-[var(--text-muted)]">
            No active sellers yet.
        </p>

        <!-- Swipeable row on phones, grid from tablet up. -->
        <div
            v-else
            class="-mx-4 mt-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:scroll-px-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4"
        >
            <Link
                v-for="seller in sellers"
                :key="seller.id"
                :href="sellerShow.url(seller.slug)"
                class="group flex w-[72%] shrink-0 snap-start flex-col overflow-hidden rounded-sm border border-[var(--border-soft)] bg-white transition hover:-translate-y-0.5 hover:border-[var(--brand-primary)] hover:shadow-[var(--shadow-hover)] sm:w-auto"
            >
                <div
                    v-if="seller.banner_url"
                    class="h-16 bg-cover bg-center"
                    :style="{ backgroundImage: `url('${seller.banner_url}')` }"
                    aria-hidden="true"
                />
                <div
                    v-else
                    class="h-16 bg-gradient-to-r from-[var(--brand-primary)] via-[var(--accent-red)] to-[var(--accent-navy)]"
                    aria-hidden="true"
                />

                <div class="flex flex-1 flex-col px-3 pb-3">
                    <div class="-mt-7 flex items-end gap-3">
                        <img
                            v-if="seller.profile_photo_url"
                            :src="seller.profile_photo_url"
                            :alt="seller.store_name"
                            class="size-14 shrink-0 rounded-full border-[3px] border-white bg-white object-cover"
                        />
                        <div
                            v-else
                            class="flex size-14 shrink-0 items-center justify-center rounded-full border-[3px] border-white bg-[var(--accent-navy)] text-lg font-semibold text-white"
                            aria-hidden="true"
                        >
                            {{ seller.store_name.charAt(0).toUpperCase() }}
                        </div>
                    </div>

                    <h3 class="mt-2 truncate text-sm font-semibold text-[var(--text-primary)] group-hover:text-[var(--brand-primary)]">
                        {{ seller.store_name }}
                    </h3>
                    <p class="mt-0.5 flex items-center gap-1 truncate text-xs text-[var(--text-muted)]">
                        <MapPin class="h-3 w-3 shrink-0" aria-hidden="true" />
                        {{ location(seller) }}
                    </p>

                    <dl class="mt-3 grid grid-cols-3 gap-1 border-t border-[var(--border-soft)] pt-3 text-center">
                        <div>
                            <dt class="sr-only">Rating</dt>
                            <dd class="flex items-center justify-center gap-0.5 text-sm font-semibold text-[var(--text-primary)]">
                                <Star class="h-3.5 w-3.5 fill-amber-400 text-amber-400" aria-hidden="true" />
                                {{ seller.ratings_count ? Number(seller.average_rating ?? 0).toFixed(1) : '–' }}
                            </dd>
                            <dd class="text-[11px] text-[var(--text-muted)]">
                                {{ seller.ratings_count ? plural(seller.ratings_count, 'review') : 'No reviews' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="sr-only">Products</dt>
                            <dd class="text-sm font-semibold text-[var(--text-primary)]">
                                {{ seller.products_count ?? 0 }}
                            </dd>
                            <dd class="text-[11px] text-[var(--text-muted)]">
                                {{ (seller.products_count ?? 0) === 1 ? 'Product' : 'Products' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="sr-only">Followers</dt>
                            <dd class="text-sm font-semibold text-[var(--text-primary)]">
                                {{ seller.followers_count ?? 0 }}
                            </dd>
                            <dd class="text-[11px] text-[var(--text-muted)]">
                                {{ (seller.followers_count ?? 0) === 1 ? 'Follower' : 'Followers' }}
                            </dd>
                        </div>
                    </dl>

                    <span
                        class="mt-3 inline-flex h-8 items-center justify-center rounded-sm border border-[var(--brand-primary)] text-xs font-medium text-[var(--brand-primary)] transition group-hover:bg-[var(--brand-primary)] group-hover:text-white"
                    >
                        Visit store
                    </span>
                </div>
            </Link>
        </div>
    </section>
</template>
