<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, ImageOff, Pencil, Star, Zap } from '@lucide/vue';
import { computed, ref } from 'vue';
import { index, edit } from '@/routes/admin/products';
import { show as orderShow } from '@/routes/admin/orders';
import { show as sellerShow } from '@/routes/admin/sellers';

type ProductImage = { id: number; url: string; is_primary: boolean; sort_order: number };

type AdminProductDetail = {
    id: number;
    name: string;
    slug: string;
    status: string;
    stock: number;
    weight_grams: number | null;
    description?: string | null;
    created_at: string | null;
    updated_at: string | null;
    seller?: { id: number; store_name: string; slug: string } | null;
    category?: { id: number; name: string } | null;
    images?: ProductImage[];
};

type RecentOrder = { order_number: string; customer_name: string; status: string; quantity: number; created_at: string | null };

const props = defineProps<{
    product: AdminProductDetail;
    public_url: string | null;
    price_formatted: string;
    stats: { sold: number; revenue_formatted: string; orders: number; rating: number | null; reviews: number };
    flash_sale: { price_formatted: string; ends_at: string | null } | null;
    recent_orders: RecentOrder[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});

const images = computed<ProductImage[]>(() =>
    [...(props.product.images ?? [])].sort((a, b) => Number(b.is_primary) - Number(a.is_primary) || a.sort_order - b.sort_order),
);
const activeImage = ref<string | null>(images.value[0]?.url ?? null);

/** Descriptions are saved as rich-text HTML; show them as plain paragraphs (no v-html). */
const descriptionParagraphs = computed<string[]>(() => {
    const html = props.product.description ?? '';

    if (!html.trim()) return [];

    const doc = new DOMParser().parseFromString(html.replace(/<\/(p|h[1-6]|li|div)>|<br\s*\/?>/gi, '$&\n'), 'text/html');

    return (doc.body.textContent ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
});

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    draft: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    inactive: 'bg-muted text-muted-foreground ring-border',
    archived: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
    pending_payment: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    paid: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    processing: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    shipped: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-900',
    completed: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
};

function label(value: string): string {
    return value.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function formatDate(value: string | null, withTime = false): string {
    if (!value) return '—';

    return new Date(value).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}

const stockLabel = computed(() => {
    const stock = props.product.stock;

    if (stock <= 0) return { text: 'Out of stock', class: 'text-red-600' };
    if (stock <= 5) return { text: `${stock} left — low`, class: 'text-amber-700 dark:text-amber-300' };

    return { text: `${stock} in stock`, class: '' };
});
</script>

<template>
    <Head :title="product.name" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Link :href="index()" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to products
        </Link>

        <!-- Header -->
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ product.name }}</h1>
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[product.status] ?? 'bg-muted text-muted-foreground ring-border'">
                        {{ label(product.status) }}
                    </span>
                    <span v-if="flash_sale" class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700 ring-1 ring-orange-200 ring-inset dark:bg-orange-950 dark:text-orange-300 dark:ring-orange-900">
                        <Zap class="size-3" aria-hidden="true" /> Flash sale
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ product.slug }} · ID {{ product.id }}
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a
                    v-if="public_url"
                    :href="public_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted"
                >
                    <ExternalLink class="size-4" aria-hidden="true" /> View in store
                </a>
                <Link :href="edit(product.id)" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                    <Pencil class="size-4" aria-hidden="true" /> Edit product
                </Link>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Price</p>
                <p class="mt-1 text-xl font-semibold">{{ flash_sale ? flash_sale.price_formatted : price_formatted }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    <template v-if="flash_sale">
                        Normal {{ price_formatted }} · ends {{ formatDate(flash_sale.ends_at, true) }}
                    </template>
                    <template v-else>Regular price</template>
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Stock</p>
                <p class="mt-1 text-xl font-semibold" :class="stockLabel.class">{{ product.stock }}</p>
                <p class="mt-0.5 text-xs" :class="stockLabel.class || 'text-muted-foreground'">{{ stockLabel.text }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Sold (paid orders)</p>
                <p class="mt-1 text-xl font-semibold">{{ stats.sold }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">{{ stats.revenue_formatted }} from {{ stats.orders }} {{ stats.orders === 1 ? 'order' : 'orders' }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Rating</p>
                <p class="mt-1 flex items-center gap-1 text-xl font-semibold">
                    <Star class="size-4 fill-amber-400 text-amber-400" aria-hidden="true" />
                    {{ stats.rating ?? '—' }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">{{ stats.reviews }} {{ stats.reviews === 1 ? 'review' : 'reviews' }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid gap-4">
                <!-- Images -->
                <section class="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="mb-3 text-base font-medium">Images</h2>
                    <div v-if="images.length" class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        <img :src="activeImage ?? images[0].url" :alt="product.name" class="aspect-square w-full max-w-md rounded-lg border object-cover sm:w-auto sm:flex-1" />
                        <div class="flex gap-2 sm:flex-col">
                            <button
                                v-for="image in images"
                                :key="image.id"
                                type="button"
                                class="relative size-16 overflow-hidden rounded-md border-2"
                                :class="(activeImage ?? images[0].url) === image.url ? 'border-[var(--brand-primary,#ee4d2d)]' : 'border-transparent'"
                                :aria-label="`Show image ${image.id}`"
                                @click="activeImage = image.url"
                            >
                                <img :src="image.url" alt="" class="size-full object-cover" />
                                <span v-if="image.is_primary" class="absolute inset-x-0 bottom-0 bg-black/60 text-center text-[9px] text-white">Main</span>
                            </button>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center gap-2 rounded-lg border border-dashed py-10 text-sm text-muted-foreground">
                        <ImageOff class="size-6" aria-hidden="true" /> No images yet.
                    </div>
                </section>

                <!-- Description -->
                <section class="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="mb-2 text-base font-medium">Description</h2>
                    <div v-if="descriptionParagraphs.length" class="space-y-2 text-sm leading-relaxed">
                        <p v-for="(paragraph, i) in descriptionParagraphs" :key="i">{{ paragraph }}</p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No description.</p>
                </section>

                <!-- Recent orders -->
                <section class="rounded-xl border bg-card shadow-sm">
                    <h2 class="p-4 pb-2 text-base font-medium">Recent orders with this product</h2>
                    <p v-if="!recent_orders.length" class="p-4 pt-0 text-sm text-muted-foreground">Not ordered yet.</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead>
                                <tr class="border-b text-xs text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Order</th>
                                    <th class="px-4 py-2 font-medium">Customer</th>
                                    <th class="px-4 py-2 text-right font-medium">Qty</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                    <th class="px-4 py-2 text-right font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in recent_orders" :key="order.order_number" class="border-b last:border-0 hover:bg-muted/50">
                                    <td class="px-4 py-2"><Link :href="orderShow(order.order_number)" class="font-medium hover:underline">{{ order.order_number }}</Link></td>
                                    <td class="px-4 py-2">{{ order.customer_name }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ order.quantity }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[order.status] ?? 'bg-muted text-muted-foreground ring-border'">{{ label(order.status) }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap text-muted-foreground">{{ formatDate(order.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Side details -->
            <aside class="grid content-start gap-4">
                <section class="rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="mb-3 text-base font-medium">Details</h2>
                    <dl class="grid gap-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Store</dt>
                            <dd class="text-right font-medium">
                                <Link v-if="product.seller" :href="sellerShow(product.seller.id)" class="hover:underline">{{ product.seller.store_name }}</Link>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Category</dt>
                            <dd class="text-right font-medium">{{ product.category?.name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Weight</dt>
                            <dd class="text-right font-medium">{{ product.weight_grams ? `${product.weight_grams} g` : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Created</dt>
                            <dd class="text-right">{{ formatDate(product.created_at, true) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Last updated</dt>
                            <dd class="text-right">{{ formatDate(product.updated_at, true) }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
</template>
