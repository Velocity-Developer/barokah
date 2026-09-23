<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ImageOff, Trash2, Upload } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/categories';
import { index as productsIndex } from '@/routes/admin/products';

export type CategoryFormValue = {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    is_active?: boolean;
    sort_order?: number;
    products_count?: number;
    active_products_count?: number;
    own_image_url?: string | null;
    image_url?: string | null;
};

const props = defineProps<{
    category?: CategoryFormValue | null;
}>();

const isEdit = computed(() => Boolean(props.category));

const form = reactive({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    is_active: props.category?.is_active ?? true,
    sort_order: String(props.category?.sort_order ?? 0),
});

const slugTouched = ref(isEdit.value);
const image = ref<File | null>(null);
const removeImage = ref(false);
const imagePreview = ref<string | null>(null);

watch(image, (file) => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = file ? URL.createObjectURL(file) : null;
    if (file) removeImage.value = false;
});

onBeforeUnmount(() => {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
});

/** What the picture box shows now: the new file, the saved one, or nothing. */
const shownImage = computed(() => imagePreview.value ?? (removeImage.value ? null : (props.category?.own_image_url ?? null)));

function pickImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    image.value = input.files?.[0] ?? null;
    input.value = '';
}
const errors = ref<Record<string, string>>({});
const isSaving = ref(false);

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/** New categories get a slug from the name until the slug is edited by hand. */
function onNameInput(): void {
    if (!slugTouched.value) form.slug = slugify(form.name);
}

const productCount = computed(() => props.category?.products_count ?? 0);

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

async function request(url: string, method: string, body?: FormData): Promise<{ ok: boolean; data: { data?: { id: number }; errors?: Record<string, string[]>; message?: string } }> {
    const response = await fetch(url, {
        // A picture means multipart, which cannot be sent as PUT.
        method: body && method === 'PUT' ? 'POST' : method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body,
    });

    const data = response.status === 204 ? {} : await response.json().catch(() => ({}));

    return { ok: response.ok, data };
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    const payload = new FormData();
    if (isEdit.value) payload.append('_method', 'PUT');
    payload.append('name', form.name.trim());
    payload.append('slug', form.slug.trim() === '' ? '' : slugify(form.slug));
    payload.append('description', form.description.trim());
    payload.append('is_active', form.is_active ? '1' : '0');
    payload.append('sort_order', String(Number(form.sort_order) || 0));
    if (image.value) payload.append('image', image.value);
    if (removeImage.value) payload.append('remove_image', '1');

    try {
        const { ok, data } = isEdit.value
            ? await request(`/api/v1/admin/categories/${props.category!.id}`, 'PUT', payload)
            : await request('/api/v1/admin/categories', 'POST', payload);

        if (!ok) {
            errors.value = Object.fromEntries(Object.entries(data.errors ?? {}).map(([field, messages]) => [field, messages[0] ?? 'Invalid value.']));
            toast.error(data.message ?? 'Category could not be saved.');
            return;
        }

        if (isEdit.value) {
            toast.success('Category saved.');
            image.value = null;
            removeImage.value = false;
            router.reload({ only: ['category'] });
        } else {
            toast.success('Category created.');
            router.visit(index().url);
        }
    } catch {
        toast.error('Categories are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}

async function remove(): Promise<void> {
    if (!props.category || !window.confirm(`Delete category "${props.category.name}"? This cannot be undone.`)) return;

    isSaving.value = true;

    try {
        const { ok, data } = await request(`/api/v1/admin/categories/${props.category.id}`, 'DELETE');

        if (!ok) {
            toast.error(data.message ?? 'Category could not be deleted.');
            return;
        }

        toast.success('Category deleted.');
        router.visit(index().url);
    } catch {
        toast.error('Categories are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <Link :href="index()" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to categories
        </Link>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading
                variant="small"
                :title="isEdit ? `Edit ${category!.name}` : 'New category'"
                :description="isEdit ? `/${category!.slug}` : 'Categories group products in the store menu, filters and homepage.'"
            />
            <div class="flex shrink-0 gap-2">
                <Link :href="index()" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : isEdit ? 'Save category' : 'Create category' }}</Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="text-base font-medium">Category details</h2>
                <div class="grid content-start gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required maxlength="255" placeholder="e.g. Keripik" @input="onNameInput" />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="slug">URL slug</Label>
                    <div class="flex items-center rounded-md border border-input focus-within:ring-2 focus-within:ring-ring/30">
                        <span class="shrink-0 pl-3 text-sm text-muted-foreground">/products?category=</span>
                        <input id="slug" v-model="form.slug" maxlength="255" class="h-9 min-w-0 flex-1 bg-transparent pr-3 text-sm outline-none" @input="slugTouched = true" />
                    </div>
                    <p class="text-xs text-muted-foreground">Lowercase letters, numbers and dashes. Leave empty to generate it from the name.</p>
                    <InputError :message="errors.slug" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="description">Description <span class="font-normal text-muted-foreground">(optional)</span></Label>
                    <textarea id="description" v-model="form.description" rows="4" maxlength="2000" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid content-start gap-2 border-t pt-4">
                    <Label for="category-image">Category picture <span class="font-normal text-muted-foreground">(optional)</span></Label>
                    <div class="flex flex-wrap items-center gap-4">
                        <img v-if="shownImage" :src="shownImage" alt="" class="size-24 rounded-lg border object-cover" />
                        <span v-else class="flex size-24 items-center justify-center rounded-lg border bg-muted text-muted-foreground" aria-hidden="true">
                            <ImageOff class="size-5" />
                        </span>
                        <div class="grid content-start gap-2">
                            <div class="flex flex-wrap gap-2">
                                <label for="category-image" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                                    <Upload class="size-4" aria-hidden="true" /> {{ shownImage ? 'Change picture' : 'Upload picture' }}
                                </label>
                                <input id="category-image" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pickImage" />
                                <button v-if="image" type="button" class="h-8 rounded-md border px-3 text-sm font-medium hover:bg-muted" @click="image = null">Undo</button>
                                <button v-else-if="category?.own_image_url" type="button" class="h-8 rounded-md border px-3 text-sm font-medium hover:bg-muted" @click="removeImage = !removeImage">
                                    {{ removeImage ? 'Keep picture' : 'Remove' }}
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Shown on the homepage category cards. Square, JPG/PNG/WebP · max 2 MB.<template v-if="removeImage"> · removed on save</template>
                            </p>
                            <p class="text-xs text-muted-foreground">Without one, a photo from a product in this category is used.</p>
                            <InputError :message="errors.image" />
                        </div>
                    </div>
                </div>
            </section>

            <aside class="grid content-start gap-4">
                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Visibility</h2>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="form.is_active" type="checkbox" class="mt-0.5 size-4 cursor-pointer accent-[var(--brand-primary,#ee4d2d)]" />
                        <span class="text-sm">
                            <span class="font-medium">Show in store</span>
                            <span class="block text-xs text-muted-foreground">Hidden categories disappear from the menu, filters and homepage. Their products stay untouched.</span>
                        </span>
                    </label>
                    <div class="grid content-start gap-2">
                        <Label for="sort_order">Display order</Label>
                        <Input id="sort_order" v-model="form.sort_order" type="number" min="0" class="w-28" />
                        <p class="text-xs text-muted-foreground">Lower numbers appear first.</p>
                        <InputError :message="errors.sort_order" />
                    </div>
                </section>

                <section v-if="isEdit" class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Products</h2>
                    <p class="text-sm">
                        <span class="font-semibold">{{ productCount }}</span> {{ productCount === 1 ? 'product' : 'products' }}
                        <span class="text-muted-foreground">· {{ category?.active_products_count ?? 0 }} active</span>
                    </p>
                    <Link :href="productsIndex({ query: { category_id: category!.id } })" class="text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">View products in this category</Link>
                </section>

                <section v-if="isEdit" class="grid content-start gap-2 rounded-xl border border-red-200 bg-card p-4 shadow-sm dark:border-red-900">
                    <h2 class="text-base font-medium text-red-700 dark:text-red-400">Delete category</h2>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="productCount > 0">Move its {{ productCount }} {{ productCount === 1 ? 'product' : 'products' }} to another category first, or hide the category instead.</template>
                        <template v-else>This cannot be undone.</template>
                    </p>
                    <Button type="button" variant="destructive" class="w-fit gap-1.5" :disabled="isSaving || productCount > 0" @click="remove">
                        <Trash2 class="size-4" aria-hidden="true" /> Delete category
                    </Button>
                </section>
            </aside>
        </div>
    </form>
</template>
