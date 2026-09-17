<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import { useSettingsStore } from '@/stores/settings';

type Product = {
    id: number;
    name: string;
    slug: string;
    price: string | number;
    normal_price?: string | number;
    effective_price?: string | number;
    flash_sale_active?: boolean;
    stock: number;
    primary_image: string | null;
};

defineProps<{ products: { data: Product[]; links: { url: string | null; label: string; active: boolean }[] } }>();
const { formatAmount } = useSettingsStore();
</script>

<template>
    <Head title="Flash Sale" />
    <MarketplaceLayout>
        <main class="mx-auto w-full max-w-[1200px] px-4 py-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-[var(--brand-primary)]">Flash Sale</h1>
                <p class="mt-1 text-sm text-muted-foreground">Promo aktif dengan harga terbatas.</p>
            </div>
            <p v-if="products.data.length === 0" class="rounded border p-8 text-center text-sm text-muted-foreground">
                Belum ada flash sale aktif.
            </p>
            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                <Link
                    v-for="product in products.data"
                    :key="product.id"
                    :href="`/products/${product.slug}`"
                    class="overflow-hidden rounded-sm border border-[var(--border-soft)] bg-white hover:shadow-[var(--shadow-hover)]"
                >
                    <div class="relative aspect-square">
                        <img v-if="product.primary_image" :src="product.primary_image" :alt="product.name" class="h-full w-full object-cover" />
                        <span class="absolute left-1 top-1 rounded-sm bg-[var(--accent-red)] px-1.5 py-0.5 text-[10px] font-bold text-white">FLASH SALE</span>
                    </div>
                    <div class="p-2">
                        <p class="line-clamp-2 min-h-9 text-xs">{{ product.name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground line-through">{{ formatAmount(Number(product.normal_price ?? product.price)) }}</p>
                        <p class="text-base font-semibold text-[var(--brand-primary)]">{{ formatAmount(Number(product.effective_price ?? product.price)) }}</p>
                        <p class="text-xs text-muted-foreground">{{ product.stock }} in stock</p>
                    </div>
                </Link>
            </div>
            <nav v-if="products.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                <Link v-for="(link, index) in products.links" :key="index" :href="link.url ?? '#'" class="rounded border px-3 py-1 text-sm" :class="link.active ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]' : 'text-muted-foreground'" v-html="link.label" />
            </nav>
        </main>
    </MarketplaceLayout>
</template>
