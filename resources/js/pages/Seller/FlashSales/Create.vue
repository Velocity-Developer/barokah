<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/seller/flash-sales';

type Product = { id: number; name: string };
const props = defineProps<{ products: Product[] }>();
const form = reactive({ product_id: '', discount_type: 'percentage', discount_value: '', quantity: '', starts_at: '', ends_at: '' });
const error = ref('');
function csrfToken(): string { return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''; }
async function save(): Promise<void> { const response = await fetch(`/api/v1/seller/products/${form.product_id}/flash-sale`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(form) }); if (!response.ok) { const body = await response.json(); error.value = Object.values(body.errors ?? {}).flat()[0] as string ?? 'Flash sale gagal disimpan.'; return; } router.visit(index()); }
</script>
<template><Head title="Tambah flash sale" /><div class="flex h-full flex-1 flex-col gap-4 p-4"><Link :href="index()" class="text-sm text-muted-foreground hover:underline">← Kembali</Link><Heading variant="small" title="Tambah flash sale" description="Buat promo flash sale untuk produk toko." /><form class="max-w-2xl space-y-4 rounded-xl border p-4" @submit.prevent="save"><select v-model="form.product_id" required class="border-input h-9 w-full rounded-md border px-3 text-sm"><option value="">Pilih produk</option><option v-for="product in props.products" :key="product.id" :value="product.id">{{ product.name }}</option></select><select v-model="form.discount_type" class="border-input h-9 w-full rounded-md border px-3 text-sm"><option value="percentage">Percentage</option><option value="fixed">Fixed price</option></select><input v-model="form.discount_value" required type="number" min="0" step="0.01" placeholder="Nilai diskon" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><input v-model="form.quantity" required type="number" min="1" placeholder="Kuota" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><input v-model="form.starts_at" required type="datetime-local" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><input v-model="form.ends_at" required type="datetime-local" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><p v-if="error" class="text-sm text-destructive">{{ error }}</p><button class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan flash sale</button></form></div></template>
