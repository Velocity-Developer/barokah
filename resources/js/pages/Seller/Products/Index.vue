<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { index, create, edit } from '@/routes/seller/products';
import { formatPrice } from '@/services/priceFormatter';

type PaginatedLinks = { url: string | null; label: string; active: boolean }[];
type PaginatedMeta = { current_page: number; last_page: number; total: number; per_page: number; from: number; to: number };

type SellerProduct = {
    id: number;
    name: string;
    slug: string;
    status: string;
    price: string | number;
    stock: number;
    category?: { name: string } | null;
};

const products = ref<SellerProduct[]>([]);
const pageLinks = ref<PaginatedLinks>([]);
const pageMeta = ref<PaginatedMeta>({ current_page: 1, last_page: 1, total: 0, per_page: 15, from: 0, to: 0 });
const isLoading = ref(true);
const error = ref<string | null>(null);

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function displayPrice(price: string | number): string {
    return formatPrice(Number(price));
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    return status === 'active' ? 'default' : status === 'archived' ? 'destructive' : 'secondary';
}

function isNumericLabel(label: string): boolean {
    return /^\d+$/.test(label);
}

async function loadProducts(page: number = 1): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
        const response = await fetch(`/api/v1/seller/products?page=${encodeURIComponent(page)}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error();
        const data = (await response.json()) as {
            data: SellerProduct[];
            links: PaginatedLinks;
            meta: PaginatedMeta;
        };
        products.value = data.data;
        pageLinks.value = data.links;
        pageMeta.value = data.meta;
    } catch {
        error.value = 'Products are temporarily unavailable.';
    } finally {
        isLoading.value = false;
    }
}

function gotoPage(page: number): void {
    if (page < 1 || page > pageMeta.value.last_page) {
        return;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
    void loadProducts(page);
}

async function removeProduct(product: SellerProduct): Promise<void> {
    if (!confirm(`Delete product "${product.name}"?`)) return;

    const response = await fetch(`/api/v1/seller/products/${product.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });

    if (response.ok) {
        if (products.value.length === 1 && pageMeta.value.current_page > 1) {
            void loadProducts(pageMeta.value.current_page - 1);
        } else {
            void loadProducts(pageMeta.value.current_page);
        }
    }
}

onMounted(() => loadProducts(1));
</script>

<template>
    <Head title="Seller products" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading variant="small" title="Seller products" description="Manage products owned by your store." />
            <Button as-child><Link :href="create()">Add product</Link></Button>
        </div>
        <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <div v-if="isLoading" class="animate-pulse space-y-2"><div class="bg-muted h-10 rounded" /><div class="bg-muted h-10 rounded" /></div>
            <p v-else-if="error" class="text-sm text-amber-600">{{ error }}</p>
            <p v-else-if="products.length === 0" class="text-muted-foreground text-sm">No products yet.</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead><tr class="text-muted-foreground border-b"><th class="px-3 py-2">Product</th><th class="px-3 py-2">Category</th><th class="px-3 py-2 text-right">Price</th><th class="px-3 py-2 text-right">Stock</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-right">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="product in products" :key="product.id" class="border-b last:border-0">
                            <td class="px-3 py-2"><div class="font-medium">{{ product.name }}</div><div class="text-muted-foreground text-xs">{{ product.slug }}</div></td>
                            <td class="px-3 py-2">{{ product.category?.name ?? '-' }}</td>
                            <td class="px-3 py-2 text-right">{{ displayPrice(product.price) }}</td>
                            <td class="px-3 py-2 text-right">{{ product.stock }}</td>
                            <td class="px-3 py-2"><Badge :variant="statusVariant(product.status)">{{ product.status }}</Badge></td>
                            <td class="px-3 py-2 text-right"><Link :href="edit(product.id)" class="mr-3 hover:underline">Edit</Link><button class="text-destructive hover:underline" @click="removeProduct(product)">Delete</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="!isLoading && !error && pageMeta.last_page > 1"
                class="mt-5 flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground text-xs">
                    Showing
                    <span class="font-medium text-slate-800">{{ pageMeta.from }}</span>
                    to
                    <span class="font-medium text-slate-800">{{ pageMeta.to }}</span>
                    of
                    <span class="font-medium text-slate-800">{{ pageMeta.total }}</span>
                    products
                    <span class="ml-1">(page {{ pageMeta.current_page }} of {{ pageMeta.last_page }})</span>
                </p>

                <nav class="flex flex-wrap items-center gap-1">
                    <button
                        type="button"
                        :disabled="pageMeta.current_page === 1"
                        class="rounded border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="gotoPage(pageMeta.current_page - 1)"
                    >
                        &laquo; Previous
                    </button>

                    <template v-for="(link, idx) in pageLinks" :key="`${link.label}-${idx}`">
                        <button
                            v-if="link.url !== null && isNumericLabel(link.label)"
                            type="button"
                            :class="
                                link.active
                                    ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-white'
                                    : 'border text-slate-700 hover:bg-slate-100'
                            "
                            class="rounded border px-3 py-1.5 text-xs font-medium"
                            @click="gotoPage(Number(link.label))"
                        >
                            {{ link.label }}
                        </button>
                        <span
                            v-else-if="!isNumericLabel(link.label)"
                            class="px-2 text-xs text-slate-400"
                        >
                            {{ link.label }}
                        </span>
                    </template>

                    <button
                        type="button"
                        :disabled="pageMeta.current_page === pageMeta.last_page"
                        class="rounded border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="gotoPage(pageMeta.current_page + 1)"
                    >
                        Next &raquo;
                    </button>
                </nav>
            </div>
        </div>
    </div>
</template>
