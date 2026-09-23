<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Zap } from '@lucide/vue';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import ProductFields, { type ProductFormFields } from '@/components/seller/ProductFields.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/seller/products';
import { index as flashSalesIndex } from '@/routes/seller/flash-sales';

type Product = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    price: string | number;
    stock: number;
    weight_grams: number;
    status: string;
    category?: { id: number } | null;
    images?: { id: number; url: string }[];
};

type Category = { id: number; name: string };

const props = defineProps<{ product: Product }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Products', href: index() }] } });

const form = reactive<ProductFormFields>({
    name: props.product.name,
    description: props.product.description ?? '',
    price: String(props.product.price),
    stock: String(props.product.stock),
    weight_grams: String(props.product.weight_grams),
    status: props.product.status,
    category_id: String(props.product.category?.id ?? ''),
});

const categories = ref<Category[]>([]);
const errors = ref<Record<string, string>>({});
const isSaving = ref(false);
const images = ref<File[]>([]);
const removeImageIds = ref<number[]>([]);

const publicUrl = computed(() => (props.product.status === 'active' ? `/products/${props.product.slug}` : null));

onMounted(async () => {
    const response = await fetch('/api/v1/categories', { headers: { Accept: 'application/json' } });

    if (response.ok) categories.value = ((await response.json()) as { data: Category[] }).data;
});

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function toggleImage(id: number): void {
    removeImageIds.value = removeImageIds.value.includes(id)
        ? removeImageIds.value.filter((imageId) => imageId !== id)
        : [...removeImageIds.value, id];
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    const data = new FormData();
    data.append('_method', 'PUT');
    for (const [key, value] of Object.entries(form)) data.append(key, value);
    for (const image of images.value) data.append('images[]', image);
    for (const id of removeImageIds.value) data.append('remove_image_ids[]', String(id));

    try {
        const response = await fetch(`/api/v1/seller/products/${props.product.id}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            body: data,
        });

        if (response.ok) {
            toast.success('Product saved.');
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
    <Head :title="`Edit ${product.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Link :href="index()" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to products
        </Link>

        <form class="flex flex-col gap-4" @submit.prevent="save">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <Heading variant="small" :title="`Edit ${product.name}`" :description="`/${product.slug}`" />
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a v-if="publicUrl" :href="publicUrl" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <ExternalLink class="size-4" aria-hidden="true" /> View in store
                    </a>
                    <Link :href="index()" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                    <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : 'Save product' }}</Button>
                </div>
            </div>

            <ProductFields
                v-model:images="images"
                :form="form"
                :errors="errors"
                :categories="categories"
                :existing-images="product.images"
                :removed-image-ids="removeImageIds"
                @toggle-image="toggleImage"
            />
        </form>

        <p class="flex flex-wrap items-center gap-1.5 rounded-xl border bg-card p-4 text-sm text-muted-foreground shadow-sm">
            <Zap class="size-4 text-[var(--brand-primary,#ee4d2d)]" aria-hidden="true" />
            Want a temporary sale price for this product?
            <Link :href="flashSalesIndex()" class="font-medium text-foreground hover:underline">Set it up in Flash sales</Link>.
        </p>
    </div>
</template>
