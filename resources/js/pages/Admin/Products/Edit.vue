<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/admin/products';
import { fetchAdminList } from '../useAdminList';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { useSettingsStore } from '@/stores/settings';
import { ArrowLeft, ImagePlus, RotateCcw, Trash2, X } from '@lucide/vue';

type ProductImage = {
    id: number;
    url: string;
    sort_order: number;
    is_primary: boolean;
};

type AdminProductDetail = {
    id: number;
    name: string;
    slug: string;
    status: string;
    price: string | number;
    stock: number;
    weight_grams: number;
    description?: string | null;
    category?: { id: number; name: string; slug: string } | null;
    seller?: { id: number; store_name: string } | null;
    images?: ProductImage[];
};

type AdminCategoryOption = {
    id: number;
    name: string;
};

const props = defineProps<{
    product: AdminProductDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Products',
                href: index(),
            },
        ],
    },
});

const statuses = [
    { value: 'active', label: 'Active', hint: 'Visible and purchasable in the store.' },
    { value: 'draft', label: 'Draft', hint: 'Hidden while it is being prepared.' },
    { value: 'inactive', label: 'Inactive', hint: 'Hidden from the store, kept for later.' },
    { value: 'archived', label: 'Archived', hint: 'Retired product; hidden from the store.' },
];

const MAX_NEW_IMAGES = 5;
const { currencySymbol } = useSettingsStore();

const form = reactive({
    name: props.product.name ?? '',
    description: props.product.description ?? '',
    price: String(props.product.price ?? ''),
    stock: String(props.product.stock ?? ''),
    weight_grams: String(props.product.weight_grams ?? '0'),
    status: props.product.status ?? 'draft',
    category_id:
        props.product.category?.id != null
            ? String(props.product.category.id)
            : '',
});

const categories = ref<AdminCategoryOption[]>([]);
const errors = ref<Record<string, string>>({});
const isSaving = ref(false);
const newImages = ref<File[]>([]);
const removeImageIds = ref<number[]>([]);

onMounted(async () => {
    try {
        categories.value = await fetchAdminList<AdminCategoryOption>(
            '/api/v1/admin/categories',
        );
    } catch {
        categories.value = [];
    }
});

function csrfToken(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
            ?.content ?? ''
    );
}

const newImagePreviews = computed(() =>
    newImages.value.map((file) => URL.createObjectURL(file)),
);

function revokeNewImagePreviews(): void {
    for (const url of newImagePreviews.value) {
        URL.revokeObjectURL(url);
    }
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files) {
        revokeNewImagePreviews();
        const room = MAX_NEW_IMAGES - newImages.value.length;
        const picked = Array.from(input.files);
        newImages.value = [...newImages.value, ...picked.slice(0, Math.max(0, room))];
        if (picked.length > room) {
            toast.error(`You can add up to ${MAX_NEW_IMAGES} images at a time.`);
        }
    }
    input.value = '';
}

function removeNewImage(index: number): void {
    newImages.value.splice(index, 1);
}

function markRemoveExistingImage(imageId: number): void {
    if (!removeImageIds.value.includes(imageId)) {
        removeImageIds.value.push(imageId);
    }
}

function undoRemoveImage(imageId: number): void {
    removeImageIds.value = removeImageIds.value.filter((id) => id !== imageId);
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('name', form.name.trim());
    formData.append('description', form.description.trim());
    formData.append('price', form.price);
    formData.append('stock', form.stock);
    formData.append('weight_grams', form.weight_grams);
    formData.append('status', form.status);

    if (form.category_id !== '') {
        formData.append('category_id', form.category_id);
    }

    for (const file of newImages.value) {
        formData.append('images[]', file);
    }

    for (const id of removeImageIds.value) {
        formData.append('remove_image_ids[]', String(id));
    }

    try {
        const response = await fetch(
            `/api/v1/admin/products/${props.product.id}`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            },
        );

        const data = (await response.json()) as {
            errors?: Record<string, string[]>;
            message?: string;
        };

        if (!response.ok) {
            const first: Record<string, string> = {};
            for (const [field, messages] of Object.entries(data.errors ?? {})) {
                first[field] = messages[0] ?? 'Invalid value.';
            }
            errors.value = first;
            toast.error(data.message ?? 'Product could not be saved.');
            return;
        }

        toast.success('Product saved.');
        newImages.value = [];
        removeImageIds.value = [];

        router.reload({ only: ['product'] });
    } catch {
        toast.error('Products are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}

async function removeProduct(): Promise<void> {
    if (!confirm(`Delete product "${props.product.name}"?`)) {
        return;
    }

    isSaving.value = true;

    try {
        const response = await fetch(
            `/api/v1/admin/products/${props.product.id}`,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );

        if (!response.ok) {
            toast.error('Product could not be deleted.');
            return;
        }

        toast.success('Product deleted.');
        window.location.href = index().url;
    } catch {
        toast.error('Products are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}

// Images marked for removal stay visible (dimmed) so they can be restored before saving.
const existingImages = computed(() =>
    [...(props.product.images ?? [])].sort(
        (a, b) => Number(b.is_primary) - Number(a.is_primary) || a.sort_order - b.sort_order,
    ),
);

const statusHint = computed(() => statuses.find((status) => status.value === form.status)?.hint ?? '');
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <Link :href="show(product.id)" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to product
        </Link>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading variant="small" :title="`Edit ${product.name}`" :description="product.slug" />
            <div class="flex shrink-0 gap-2">
                <Link :href="show(product.id)" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : 'Save product' }}</Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid content-start gap-4">
                <!-- Basic info -->
                <section class="grid gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Product information</h2>
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" type="text" required maxlength="255" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <RichTextEditor v-model="form.description" />
                        <InputError :message="errors.description" />
                    </div>
                </section>

                <!-- Images -->
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="text-base font-medium">Images</h2>
                        <p class="text-xs text-muted-foreground">JPG, PNG or WebP · max 2 MB each · up to {{ MAX_NEW_IMAGES }} new per save</p>
                    </div>

                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                        <div
                            v-for="image in existingImages"
                            :key="image.id"
                            class="group relative aspect-square overflow-hidden rounded-lg border"
                        >
                            <img
                                :src="image.url"
                                :alt="`Image ${image.id}`"
                                class="size-full object-cover transition"
                                :class="removeImageIds.includes(image.id) ? 'opacity-30 grayscale' : ''"
                            />
                            <span v-if="image.is_primary && !removeImageIds.includes(image.id)" class="absolute top-1.5 left-1.5 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-medium text-white">Main</span>
                            <span v-if="removeImageIds.includes(image.id)" class="absolute inset-x-0 top-1/2 -translate-y-1/2 text-center text-xs font-medium text-red-600">Will be removed</span>
                            <button
                                v-if="!removeImageIds.includes(image.id)"
                                type="button"
                                class="absolute top-1.5 right-1.5 flex size-7 items-center justify-center rounded-full bg-white/90 text-red-600 shadow hover:bg-white"
                                :aria-label="`Remove image ${image.id}`"
                                @click="markRemoveExistingImage(image.id)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                            <button
                                v-else
                                type="button"
                                class="absolute top-1.5 right-1.5 flex size-7 items-center justify-center rounded-full bg-white/90 text-foreground shadow hover:bg-white"
                                :aria-label="`Keep image ${image.id}`"
                                @click="undoRemoveImage(image.id)"
                            >
                                <RotateCcw class="size-3.5" />
                            </button>
                        </div>

                        <div v-for="(file, idx) in newImages" :key="`new-${idx}`" class="relative aspect-square overflow-hidden rounded-lg border-2 border-dashed border-[var(--brand-primary,#ee4d2d)]">
                            <img :src="newImagePreviews[idx]" :alt="file.name" class="size-full object-cover" />
                            <span class="absolute top-1.5 left-1.5 rounded bg-[var(--brand-primary,#ee4d2d)] px-1.5 py-0.5 text-[10px] font-medium text-white">New</span>
                            <button
                                type="button"
                                class="absolute top-1.5 right-1.5 flex size-7 items-center justify-center rounded-full bg-white/90 shadow hover:bg-white"
                                :aria-label="`Remove ${file.name}`"
                                @click="removeNewImage(idx)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>

                        <label
                            v-if="newImages.length < MAX_NEW_IMAGES"
                            for="images"
                            class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed text-xs text-muted-foreground transition hover:border-[var(--brand-primary,#ee4d2d)] hover:text-foreground"
                        >
                            <ImagePlus class="size-6" aria-hidden="true" />
                            Add images
                        </label>
                        <input id="images" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="onFileChange" />
                    </div>

                    <p v-if="removeImageIds.length || newImages.length" class="text-xs text-muted-foreground">
                        On save: {{ newImages.length }} added, {{ removeImageIds.length }} removed.
                    </p>
                    <InputError :message="errors['images.0'] ?? errors.images" />
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <!-- Status -->
                <section class="grid gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <Label for="status" class="text-base font-medium">Status</Label>
                    <select id="status" v-model="form.status" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                    <p class="text-xs text-muted-foreground">{{ statusHint }}</p>
                    <InputError :message="errors.status" />
                </section>

                <!-- Organization -->
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Organization</h2>
                    <div class="grid gap-1">
                        <p class="text-sm font-medium">Store</p>
                        <p class="text-sm text-muted-foreground">{{ product.seller?.store_name ?? '—' }}</p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="category_id">Category</Label>
                        <select id="category_id" v-model="form.category_id" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
                            <option v-if="!categories.length && product.category" :value="String(product.category.id)">{{ product.category.name }}</option>
                            <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                        </select>
                        <InputError :message="errors.category_id" />
                    </div>
                </section>

                <!-- Pricing & inventory -->
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Pricing &amp; inventory</h2>
                    <div class="grid gap-2">
                        <Label for="price">Price</Label>
                        <div class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">{{ currencySymbol() }}</span>
                            <Input id="price" v-model="form.price" type="number" step="0.01" min="0" required class="pl-10" />
                        </div>
                        <InputError :message="errors.price" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="stock">Stock</Label>
                            <Input id="stock" v-model="form.stock" type="number" min="0" required />
                            <InputError :message="errors.stock" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="weight_grams">Weight (g)</Label>
                            <Input id="weight_grams" v-model="form.weight_grams" type="number" min="0" required />
                            <InputError :message="errors.weight_grams" />
                        </div>
                    </div>
                </section>

                <!-- Danger zone -->
                <section class="grid gap-2 rounded-xl border border-red-200 bg-card p-4 shadow-sm dark:border-red-900">
                    <h2 class="text-base font-medium text-red-700 dark:text-red-400">Delete product</h2>
                    <p class="text-xs text-muted-foreground">Removes the product and its images. This cannot be undone.</p>
                    <Button type="button" variant="destructive" class="w-fit" :disabled="isSaving" @click="removeProduct">Delete product</Button>
                </section>
            </aside>
        </div>
    </form>
</template>
