<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import ProductFields, { type ProductFormFields } from '@/components/seller/ProductFields.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/seller/products';

type Category = { id: number; name: string };

defineOptions({ layout: { breadcrumbs: [{ title: 'Products', href: index() }] } });

const form = reactive<ProductFormFields>({
    name: '',
    description: '',
    price: '',
    stock: '0',
    weight_grams: '0',
    status: 'draft',
    category_id: '',
});

const categories = ref<Category[]>([]);
const errors = ref<Record<string, string>>({});
const isSaving = ref(false);
const images = ref<File[]>([]);

onMounted(async () => {
    const response = await fetch('/api/v1/categories', { headers: { Accept: 'application/json' } });

    if (response.ok) categories.value = ((await response.json()) as { data: Category[] }).data;
});

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    const data = new FormData();
    for (const [key, value] of Object.entries(form)) data.append(key, value);
    for (const image of images.value) data.append('images[]', image);

    try {
        const response = await fetch('/api/v1/seller/products', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            body: data,
        });

        if (response.ok) {
            toast.success(`${form.name} added.`);
            router.visit(index());

            return;
        }

        const body = (await response.json()) as { errors?: Record<string, string[]>; message?: string };
        for (const [field, messages] of Object.entries(body.errors ?? {})) errors.value[field] = messages[0] ?? 'Invalid value.';
        toast.error(body.message ?? 'This product could not be saved.');
    } catch {
        toast.error('Products are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head title="Add product" />

    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <Link :href="index()" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to products
        </Link>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading variant="small" title="Add product" description="Set it to Active when you are ready for it to appear in your store." />
            <div class="flex shrink-0 gap-2">
                <Link :href="index()" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : 'Save product' }}</Button>
            </div>
        </div>

        <ProductFields v-model:images="images" :form="form" :errors="errors" :categories="categories" />
    </form>
</template>
