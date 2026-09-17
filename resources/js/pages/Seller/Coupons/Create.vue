<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'; import { reactive, ref } from 'vue'; import Heading from '@/components/Heading.vue';
const form=reactive({code:'',name:'',discount_type:'percentage',discount_value:'',maximum_discount:'',minimum_spend:'0',usage_limit:'',per_user_limit:'',starts_at:'',ends_at:'',allow_flash_sale:false,status:true}); const error=ref(''); async function save():Promise<void>{const r=await fetch('/api/v1/seller/coupons',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':(document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content??''},body:JSON.stringify(form)});if(!r.ok){error.value='Gagal menyimpan coupon.';return}location.href='/seller/coupons'}
</script>
<template>
    <Head title="Tambah coupon" />
    <div class="flex w-full flex-col gap-4 p-4">
        <Link href="/seller/coupons">← Kembali</Link>
        <Heading variant="small" title="Tambah coupon" description="Buat coupon toko." />
        <form class="w-full space-y-5 rounded-xl border p-5" @submit.prevent="save">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="space-y-1.5"><span class="text-sm font-medium">Kode coupon</span><input v-model="form.code" required class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Nama coupon</span><input v-model="form.name" required class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Jenis diskon</span><select v-model="form.discount_type" class="h-9 w-full rounded border px-3"><option value="percentage">Persentase</option><option value="fixed">Nominal tetap</option><option value="free_shipping">Gratis ongkir</option></select></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Nilai diskon</span><input v-model="form.discount_value" required type="number" min="0" step=".01" class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Diskon maksimum</span><input v-model="form.maximum_discount" type="number" min="0" step=".01" class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Minimum belanja</span><input v-model="form.minimum_spend" type="number" min="0" step=".01" class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Batas penggunaan</span><input v-model="form.usage_limit" type="number" min="1" class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Batas per pengguna</span><input v-model="form.per_user_limit" type="number" min="1" class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Mulai berlaku</span><input v-model="form.starts_at" required type="datetime-local" class="h-9 w-full rounded border px-3" /></label>
                <label class="space-y-1.5"><span class="text-sm font-medium">Berakhir</span><input v-model="form.ends_at" required type="datetime-local" class="h-9 w-full rounded border px-3" /></label>
            </div>
            <div class="flex flex-wrap gap-5 border-t pt-4">
                <label class="flex items-center gap-2 text-sm"><input v-model="form.allow_flash_sale" type="checkbox" /> Izinkan flash sale</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="form.status" type="checkbox" /> Aktif</label>
            </div>
            <p v-if="error" class="text-destructive">{{ error }}</p>
            <button class="rounded bg-primary px-4 py-2 text-primary-foreground">Simpan</button>
        </form>
    </div>
</template>
