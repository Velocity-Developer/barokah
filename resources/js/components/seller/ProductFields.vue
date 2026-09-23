<script setup lang="ts">
import { ImageOff, Upload } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ProductFormFields = {
    name: string;
    description: string;
    price: string;
    stock: string;
    weight_grams: string;
    status: string;
    category_id: string;
};

type ExistingImage = { id: number; url: string };

/**
 * The product fields shared by the seller's add and edit pages. `form` is a
 * reactive object owned by the page; this component writes into it directly.
 */
const props = defineProps<{
    form: ProductFormFields;
    errors: Record<string, string>;
    categories: { id: number; name: string }[];
    existingImages?: ExistingImage[];
    removedImageIds?: number[];
}>();

const emit = defineEmits<{ toggleImage: [id: number] }>();

const newImages = defineModel<File[]>('images', { default: () => [] });

const STATUSES = [
    { value: 'draft', label: 'Draft', hint: 'Only you can see it.' },
    { value: 'active', label: 'Active', hint: 'On sale in your store.' },
    { value: 'inactive', label: 'Inactive', hint: 'Hidden from shoppers, kept in your catalogue.' },
    { value: 'archived', label: 'Archived', hint: 'Put away; hidden from shoppers.' },
];

const statusHint = computed(() => STATUSES.find((status) => status.value === props.form.status)?.hint ?? '');

const previews = ref<string[]>([]);

watch(
    newImages,
    (files) => {
        previews.value.forEach((url) => URL.revokeObjectURL(url));
        previews.value = files.map((file) => URL.createObjectURL(file));
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => previews.value.forEach((url) => URL.revokeObjectURL(url)));

function onFiles(event: Event): void {
    newImages.value = Array.from((event.target as HTMLInputElement).files ?? []);
}

function isRemoved(id: number): boolean {
    return (props.removedImageIds ?? []).includes(id);
}
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="grid content-start gap-4">
            <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="text-base font-medium">Product details</h2>

                <div class="grid content-start gap-2">
                    <Label for="name">Product name</Label>
                    <Input id="name" v-model="form.name" required maxlength="255" placeholder="Keripik Pisang Original" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="description">Description</Label>
                    <RichTextEditor v-model="form.description" />
                    <p class="text-xs text-muted-foreground">Tell shoppers what it is, what is inside and how it is packed.</p>
                    <InputError :message="errors.description" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="category_id">Category</Label>
                    <select id="category_id" v-model="form.category_id" required class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                        <option value="">Select a category</option>
                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                    </select>
                    <InputError :message="errors.category_id" />
                </div>
            </section>

            <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="text-base font-medium">Photos</h2>

                <div v-if="existingImages?.length" class="flex flex-wrap gap-3">
                    <div v-for="image in existingImages" :key="image.id" class="grid gap-1">
                        <img :src="image.url" alt="" class="size-24 rounded-lg border object-cover" :class="isRemoved(image.id) ? 'opacity-40' : ''" />
                        <button type="button" class="text-xs font-medium hover:underline" :class="isRemoved(image.id) ? 'text-muted-foreground' : 'text-red-600'" @click="emit('toggleImage', image.id)">
                            {{ isRemoved(image.id) ? 'Keep' : 'Remove' }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <label for="images" class="inline-flex h-9 w-fit cursor-pointer items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <Upload class="size-4" aria-hidden="true" /> {{ existingImages?.length ? 'Add more photos' : 'Upload photos' }}
                    </label>
                    <input id="images" type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="onFiles" />
                    <p class="text-xs text-muted-foreground">JPG, PNG or WebP. The first photo is the one shoppers see in listings.</p>
                    <InputError :message="errors.images" />
                </div>

                <div v-if="previews.length" class="flex flex-wrap gap-3">
                    <img v-for="(preview, index) in previews" :key="preview" :src="preview" :alt="`New photo ${index + 1}`" class="size-24 rounded-lg border object-cover" />
                </div>
                <p v-else-if="!existingImages?.length" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <ImageOff class="size-4" aria-hidden="true" /> No photo yet.
                </p>
            </section>
        </div>

        <div class="grid content-start gap-4">
            <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="text-base font-medium">Price &amp; stock</h2>

                <div class="grid content-start gap-2">
                    <Label for="price">Price</Label>
                    <Input id="price" v-model="form.price" type="number" min="0" step="0.01" required placeholder="0.00" />
                    <InputError :message="errors.price" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="stock">Stock</Label>
                    <Input id="stock" v-model="form.stock" type="number" min="0" required />
                    <InputError :message="errors.stock" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="weight_grams">Weight (grams)</Label>
                    <Input id="weight_grams" v-model="form.weight_grams" type="number" min="0" required />
                    <p class="text-xs text-muted-foreground">Used to work out the shipping fee.</p>
                    <InputError :message="errors.weight_grams" />
                </div>
            </section>

            <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                <h2 class="text-base font-medium">Status</h2>
                <select id="status" v-model="form.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                    <option v-for="status in STATUSES" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <p class="text-xs text-muted-foreground">{{ statusHint }}</p>
                <InputError :message="errors.status" />
            </section>
        </div>
    </div>
</template>
