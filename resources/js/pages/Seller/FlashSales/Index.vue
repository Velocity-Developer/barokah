<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { formatPrice } from '@/services/priceFormatter';
import { create as createFlashSale, edit as editFlashSale } from '@/routes/seller/flash-sales';

type Sale = {
    id: number;
    product: { id: number; name: string; slug: string } | null;
    price: string | number;
    quantity: number;
    quantity_sold: number;
    starts_at: string;
    ends_at: string;
    status: string;
};
type Product = { id: number; name: string };
const props = defineProps<{ flashSales: { data: Sale[]; links: { url: string | null; label: string; active: boolean }[] } }>();
const products = ref<Product[]>([]);
const form = reactive({ product_id: '', discount_type: 'percentage', discount_value: '', quantity: '', starts_at: '', ends_at: '' });
const error = ref('');
defineOptions({ layout: { breadcrumbs: [{ title: 'Flash sales', href: '/seller/flash-sales' }] } });
function csrfToken(): string { return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''; }
onMounted(async () => { const response = await fetch('/api/v1/seller/products', { credentials: 'same-origin', headers: { Accept: 'application/json' } }); if (response.ok) products.value = ((await response.json()) as { data: Product[] }).data; });
async function createSale(): Promise<void> { error.value = ''; const response = await fetch(`/api/v1/seller/products/${form.product_id}/flash-sale`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ ...form }) }); if (!response.ok) { const body = await response.json(); error.value = Object.values(body.errors ?? {}).flat()[0] as string ?? 'Flash sale gagal disimpan.'; return; } router.reload(); }
async function removeSale(sale: Sale): Promise<void> { if (!sale.product || !confirm(`Hapus flash sale ${sale.product.name}?`)) return; await fetch(`/api/v1/seller/products/${sale.product.id}/flash-sale`, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' } }); router.reload(); }
</script>
<template>
    <Head title="Seller flash sales" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between"><Heading variant="small" title="Flash sales" description="Flash sale products from your store." /><Link :href="createFlashSale()" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Tambah flash sale</Link></div>
        
            <div class="overflow-x-auto rounded-xl border"><table class="w-full text-left text-sm"><thead class="bg-muted"><tr><th class="p-3">Product</th><th class="p-3">Price</th><th class="p-3">Quota</th><th class="p-3">Period</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="sale in props.flashSales.data" :key="sale.id" class="border-t"><td class="p-3">{{ sale.product?.name ?? '-' }}</td><td class="p-3">{{ formatPrice(Number(sale.price)) }}</td><td class="p-3">{{ sale.quantity_sold }} / {{ sale.quantity }}</td><td class="p-3">{{ new Date(sale.starts_at).toLocaleDateString() }} - {{ new Date(sale.ends_at).toLocaleDateString() }}</td><td class="p-3">{{ sale.status }}</td><td class="flex gap-2 p-3"><Link v-if="sale.product" :href="editFlashSale(sale.id)" class="text-primary hover:underline">Edit</Link><button class="text-destructive hover:underline" @click="removeSale(sale)">Delete</button></td></tr><tr v-if="props.flashSales.data.length === 0"><td colspan="6" class="p-6 text-center text-muted-foreground">No flash sales in this store.</td></tr></tbody></table></div>
        <nav class="flex gap-1"><Link v-for="(link, index) in props.flashSales.links" :key="index" :href="link.url ?? '#'" class="rounded border px-3 py-1 text-sm" :class="link.active ? 'font-semibold' : 'text-muted-foreground'" v-html="link.label" /></nav>
    </div>
</template>
