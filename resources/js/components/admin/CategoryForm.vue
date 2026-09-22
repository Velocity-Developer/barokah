<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
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

async function request(url: string, method: string, body?: Record<string, unknown>): Promise<{ ok: boolean; data: { data?: { id: number }; errors?: Record<string, string[]>; message?: string } }> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = response.status === 204 ? {} : await response.json().catch(() => ({}));

    return { ok: response.ok, data };
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    const payload = {
        name: form.name.trim(),
        slug: form.slug.trim() === '' ? null : slugify(form.slug),
        description: form.description.trim() === '' ? null : form.description.trim(),
        is_active: form.is_active,
        sort_order: Number(form.sort_order) || 0,
    };

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
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" required maxlength="255" placeholder="e.g. Keripik" @input="onNameInput" />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="slug">URL slug</Label>
                    <div class="flex items-center rounded-md border border-input focus-within:ring-2 focus-within:ring-ring/30">
                        <span class="shrink-0 pl-3 text-sm text-muted-foreground">/products?category=</span>
                        <input id="slug" v-model="form.slug" maxlength="255" class="h-9 min-w-0 flex-1 bg-transparent pr-3 text-sm outline-none" @input="slugTouched = true" />
                    </div>
                    <p class="text-xs text-muted-foreground">Lowercase letters, numbers and dashes. Leave empty to generate it from the name.</p>
                    <InputError :message="errors.slug" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">Description <span class="font-normal text-muted-foreground">(optional)</span></Label>
                    <textarea id="description" v-model="form.description" rows="4" maxlength="2000" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                    <InputError :message="errors.description" />
                </div>
            </section>

            <aside class="grid content-start gap-4">
                <section class="grid gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Visibility</h2>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="form.is_active" type="checkbox" class="mt-0.5 size-4 cursor-pointer accent-[var(--brand-primary,#ee4d2d)]" />
                        <span class="text-sm">
                            <span class="font-medium">Show in store</span>
                            <span class="block text-xs text-muted-foreground">Hidden categories disappear from the menu, filters and homepage. Their products stay untouched.</span>
                        </span>
                    </label>
                    <div class="grid gap-2">
                        <Label for="sort_order">Display order</Label>
                        <Input id="sort_order" v-model="form.sort_order" type="number" min="0" class="w-28" />
                        <p class="text-xs text-muted-foreground">Lower numbers appear first.</p>
                        <InputError :message="errors.sort_order" />
                    </div>
                </section>

                <section v-if="isEdit" class="grid gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Products</h2>
                    <p class="text-sm">
                        <span class="font-semibold">{{ productCount }}</span> {{ productCount === 1 ? 'product' : 'products' }}
                        <span class="text-muted-foreground">· {{ category?.active_products_count ?? 0 }} active</span>
                    </p>
                    <Link :href="productsIndex({ query: { category_id: category!.id } })" class="text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">View products in this category</Link>
                </section>

                <section v-if="isEdit" class="grid gap-2 rounded-xl border border-red-200 bg-card p-4 shadow-sm dark:border-red-900">
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
