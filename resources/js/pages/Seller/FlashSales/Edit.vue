<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { index } from '@/routes/seller/flash-sales';

type Sale = { id: number; product: { id: number; name: string }; discount_type: string; discount_value: string | number; quantity: number; starts_at: string; ends_at: string };
const props = defineProps<{ flashSale: Sale }>();
const form = reactive({ discount_type: props.flashSale.discount_type, discount_value: String(props.flashSale.discount_value), quantity: String(props.flashSale.quantity), starts_at: props.flashSale.starts_at.slice(0, 16), ends_at: props.flashSale.ends_at.slice(0, 16) });
const error = ref('');
function csrfToken(): string { return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''; }
async function save(): Promise<void> { const response = await fetch(`/api/v1/seller/products/${props.flashSale.product.id}/flash-sale`, { method: 'PUT', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(form) }); if (!response.ok) { const body = await response.json(); error.value = Object.values(body.errors ?? {}).flat()[0] as string ?? 'Flash sale gagal diperbarui.'; return; } router.visit(index()); }
</script>
<template><Head title="Edit flash sale" /><div class="flex h-full flex-1 flex-col gap-4 p-4"><Link :href="index()" class="text-sm text-muted-foreground hover:underline">← Kembali</Link><Heading variant="small" title="Edit flash sale" :description="`Produk: ${flashSale.product.name}`" /><form class="max-w-2xl space-y-4 rounded-xl border p-4" @submit.prevent="save"><select v-model="form.discount_type" class="border-input h-9 w-full rounded-md border px-3 text-sm"><option value="percentage">Percentage</option><option value="fixed">Fixed price</option></select><input v-model="form.discount_value" required type="number" min="0" step="0.01" placeholder="Nilai diskon" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><input v-model="form.quantity" required type="number" min="1" placeholder="Kuota" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><input v-model="form.starts_at" required type="datetime-local" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><input v-model="form.ends_at" required type="datetime-local" class="border-input h-9 w-full rounded-md border px-3 text-sm" /><p v-if="error" class="text-sm text-destructive">{{ error }}</p><button class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground">Simpan perubahan</button></form></div></template>
