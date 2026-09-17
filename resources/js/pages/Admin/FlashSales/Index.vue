<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { create as createFlashSale, edit as editFlashSale } from '@/routes/admin/flash-sales';

type Sale = { id: number; product: { name: string; slug: string; seller: { store_name: string } | null } | null; price: string | number; quantity: number; quantity_sold: number; starts_at: string; ends_at: string; status: string };
const props = defineProps<{ flashSales: { data: Sale[]; links: { url: string | null; label: string; active: boolean }[] } }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Flash sales', href: '/admin/flash-sales' }] } });
function csrfToken(): string { return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''; }
async function removeSale(sale: Sale): Promise<void> { if (!confirm(`Hapus flash sale ${sale.product?.name ?? ''}?`)) return; await fetch(`/api/v1/admin/flash-sales/${sale.id}`, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' } }); router.reload(); }
</script>
<template>
    <Head title="Admin flash sales" />
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-start justify-between"><Heading variant="small" title="Flash sales" description="All flash sales from every seller." /><Link :href="createFlashSale()" class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Tambah flash sale</Link></div>
        <div class="overflow-x-auto rounded-xl border"><table class="w-full text-left text-sm"><thead class="bg-muted"><tr><th class="p-3">Product</th><th class="p-3">Seller</th><th class="p-3">Price</th><th class="p-3">Quota</th><th class="p-3">Period</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="sale in props.flashSales.data" :key="sale.id" class="border-t"><td class="p-3">{{ sale.product?.name ?? '-' }}</td><td class="p-3">{{ sale.product?.seller?.store_name ?? '-' }}</td><td class="p-3">{{ sale.price }}</td><td class="p-3">{{ sale.quantity_sold }} / {{ sale.quantity }}</td><td class="p-3">{{ new Date(sale.starts_at).toLocaleDateString() }} - {{ new Date(sale.ends_at).toLocaleDateString() }}</td><td class="p-3">{{ sale.status }}</td><td class="flex gap-2 p-3"><Link :href="editFlashSale(sale.id)" class="text-primary hover:underline">Edit</Link><button class="text-destructive hover:underline" @click="removeSale(sale)">Delete</button></td></tr><tr v-if="props.flashSales.data.length === 0"><td colspan="7" class="p-6 text-center text-muted-foreground">No flash sales found.</td></tr></tbody></table></div>
        <nav class="flex gap-1"><Link v-for="(link, index) in props.flashSales.links" :key="index" :href="link.url ?? '#'" class="rounded border px-3 py-1 text-sm" :class="link.active ? 'font-semibold' : 'text-muted-foreground'" v-html="link.label" /></nav>
    </div>
</template>
