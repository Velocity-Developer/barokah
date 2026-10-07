<script setup lang="ts">
import { ImagePlus, Layers, Plus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    MAX_OPTIONS,
    MAX_TYPES,
    emptyOption,
    emptyType,
    ensureVariants,
    variantRows,
    variationError,
    type OptionDraft,
    type VariationDraft,
} from '@/lib/productVariations';

/**
 * Shopee-style variations for the seller and admin product forms: up to two
 * types (Colour × Size), a photo per first-type option, and a price, stock
 * and optional weight for every combination. `draft` is owned by the page.
 */
const props = defineProps<{
    draft: VariationDraft;
    errors: Record<string, string>;
    currency?: string;
}>();

const rows = computed(() => variantRows(props.draft.types));
const error = computed(() => variationError(props.errors));
const bulk = reactive({ price: '', stock: '', weight_grams: '' });
const previews = new Map<File, string>();

watch(() => props.draft.types, () => ensureVariants(props.draft), { deep: true, immediate: true });

onBeforeUnmount(() => previews.forEach((url) => URL.revokeObjectURL(url)));

function enable(): void {
    props.draft.enabled = true;
    if (props.draft.types.length === 0) props.draft.types.push(emptyType());
}

function disable(): void {
    if (!window.confirm('Remove all variations? The product will be sold as one item with the price and stock you fill in.')) return;
    props.draft.enabled = false;
    props.draft.types.splice(0);
}

function addType(): void {
    if (props.draft.types.length < MAX_TYPES) props.draft.types.push(emptyType());
}

function removeType(index: number): void {
    if (props.draft.types.length === 1) {
        disable();

        return;
    }
    props.draft.types.splice(index, 1);
}

function addOption(typeIndex: number): void {
    const type = props.draft.types[typeIndex];
    if (type.options.length < MAX_OPTIONS) type.options.push(emptyOption());
}

function removeOption(typeIndex: number, optionIndex: number): void {
    const type = props.draft.types[typeIndex];
    if (type.options.length > 1) type.options.splice(optionIndex, 1);
}

function photoOf(option: OptionDraft): string | null {
    if (option.newImage) {
        if (!previews.has(option.newImage)) previews.set(option.newImage, URL.createObjectURL(option.newImage));

        return previews.get(option.newImage) ?? null;
    }

    return option.removeImage ? null : option.imageUrl;
}

function pickPhoto(option: OptionDraft, event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) {
        option.newImage = file;
        option.removeImage = false;
    }
    input.value = '';
}

function clearPhoto(option: OptionDraft): void {
    option.newImage = null;
    option.removeImage = option.imageUrl !== null;
}

function applyToAll(): void {
    for (const row of rows.value) {
        const values = props.draft.variants[row.key];
        if (!values) continue;
        if (bulk.price !== '') values.price = bulk.price;
        if (bulk.stock !== '') values.stock = bulk.stock;
        if (bulk.weight_grams !== '') values.weight_grams = bulk.weight_grams;
    }
}

function optionLabel(option: OptionDraft, position: number): string {
    return option.name.trim() || `Option ${position + 1}`;
}
</script>

<template>
    <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-medium">Variations</h2>
                <p class="text-xs text-muted-foreground">
                    For a product sold in several colours, sizes or models. Shoppers pick one, and each choice has its own price and stock.
                </p>
            </div>
            <Button v-if="draft.enabled" type="button" variant="ghost" size="sm" class="text-red-600 hover:text-red-700" @click="disable">
                Remove variations
            </Button>
        </div>

        <Button v-if="!draft.enabled" type="button" variant="outline" class="w-fit" @click="enable">
            <Layers aria-hidden="true" /> Add variations
        </Button>

        <template v-else>
            <div v-for="(type, typeIndex) in draft.types" :key="type.key" class="grid content-start gap-3 rounded-lg border bg-muted/30 p-3">
                <div class="flex items-end gap-2">
                    <div class="grid flex-1 content-start gap-2">
                        <Label :for="`variation-type-${type.key}`">Variation {{ typeIndex + 1 }}</Label>
                        <Input
                            :id="`variation-type-${type.key}`"
                            v-model="type.name"
                            required
                            maxlength="30"
                            :placeholder="typeIndex === 0 ? 'e.g. Colour' : 'e.g. Size'"
                        />
                    </div>
                    <Button type="button" variant="ghost" size="icon" :aria-label="`Remove variation ${typeIndex + 1}`" @click="removeType(typeIndex)">
                        <X aria-hidden="true" />
                    </Button>
                </div>

                <div class="grid content-start gap-2">
                    <p class="text-sm font-medium">Options</p>
                    <div v-for="(option, optionIndex) in type.options" :key="option.key" class="flex items-center gap-2">
                        <div v-if="typeIndex === 0" class="relative shrink-0">
                            <label
                                :for="`variation-photo-${option.key}`"
                                class="flex size-9 cursor-pointer items-center justify-center overflow-hidden rounded-md border border-dashed bg-background text-muted-foreground hover:bg-muted"
                                :title="photoOf(option) ? 'Change photo' : 'Add photo'"
                            >
                                <img v-if="photoOf(option)" :src="photoOf(option) ?? ''" alt="" class="size-full object-cover" />
                                <ImagePlus v-else class="size-4" aria-hidden="true" />
                                <span class="sr-only">Photo for {{ optionLabel(option, optionIndex) }}</span>
                            </label>
                            <input
                                :id="`variation-photo-${option.key}`"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="pickPhoto(option, $event)"
                            />
                            <button
                                v-if="photoOf(option)"
                                type="button"
                                class="absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full bg-foreground text-background"
                                :aria-label="`Remove photo of ${optionLabel(option, optionIndex)}`"
                                @click="clearPhoto(option)"
                            >
                                <X class="size-3" aria-hidden="true" />
                            </button>
                        </div>
                        <Input
                            v-model="option.name"
                            required
                            maxlength="50"
                            class="flex-1"
                            :aria-label="`${type.name || `Variation ${typeIndex + 1}`} option ${optionIndex + 1}`"
                            :placeholder="typeIndex === 0 ? 'e.g. Black' : 'e.g. M'"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="type.options.length === 1"
                            :aria-label="`Remove option ${optionLabel(option, optionIndex)}`"
                            @click="removeOption(typeIndex, optionIndex)"
                        >
                            <X aria-hidden="true" />
                        </Button>
                    </div>
                    <Button v-if="type.options.length < MAX_OPTIONS" type="button" variant="outline" size="sm" class="w-fit" @click="addOption(typeIndex)">
                        <Plus aria-hidden="true" /> Add option
                    </Button>
                    <p v-if="typeIndex === 0" class="text-xs text-muted-foreground">
                        Optional photo per option: shoppers see it when they pick that option.
                    </p>
                </div>
            </div>

            <Button v-if="draft.types.length < MAX_TYPES" type="button" variant="outline" size="sm" class="w-fit" @click="addType">
                <Plus aria-hidden="true" /> Add a second variation (e.g. Size)
            </Button>

            <div class="grid content-start gap-2">
                <p class="text-sm font-medium">Price &amp; stock per variation</p>

                <div class="flex flex-wrap items-end gap-2 rounded-lg border border-dashed p-2">
                    <div class="grid w-28 content-start gap-1">
                        <Label for="variation-bulk-price" class="text-xs">Price</Label>
                        <Input id="variation-bulk-price" v-model="bulk.price" type="number" min="0.01" step="0.01" placeholder="0.00" />
                    </div>
                    <div class="grid w-24 content-start gap-1">
                        <Label for="variation-bulk-stock" class="text-xs">Stock</Label>
                        <Input id="variation-bulk-stock" v-model="bulk.stock" type="number" min="0" placeholder="0" />
                    </div>
                    <div class="grid w-28 content-start gap-1">
                        <Label for="variation-bulk-weight" class="text-xs">Weight (g)</Label>
                        <Input id="variation-bulk-weight" v-model="bulk.weight_grams" type="number" min="0" placeholder="Default" />
                    </div>
                    <Button type="button" variant="secondary" size="sm" class="h-9" @click="applyToAll">Apply to all</Button>
                </div>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[480px] text-sm">
                        <thead class="bg-muted/50 text-left text-xs text-muted-foreground">
                            <tr>
                                <th v-for="(type, typeIndex) in draft.types" :key="type.key" scope="col" class="px-3 py-2 font-medium">
                                    {{ type.name.trim() || `Variation ${typeIndex + 1}` }}
                                </th>
                                <th scope="col" class="px-3 py-2 font-medium">Price{{ currency ? ` (${currency})` : '' }}</th>
                                <th scope="col" class="px-3 py-2 font-medium">Stock</th>
                                <th scope="col" class="px-3 py-2 font-medium">Weight (g)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in rows" :key="row.key">
                                <td v-for="(option, level) in row.options" :key="option.key" class="px-3 py-2 align-middle">
                                    {{ optionLabel(option, row.positions[level]) }}
                                </td>
                                <template v-if="draft.variants[row.key]">
                                    <td class="px-2 py-1.5">
                                        <Input
                                            v-model="draft.variants[row.key].price"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            placeholder="0.00"
                                            class="h-8 w-28"
                                            :aria-label="`Price of ${row.options.map((option, level) => optionLabel(option, row.positions[level])).join(', ')}`"
                                        />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <Input
                                            v-model="draft.variants[row.key].stock"
                                            type="number"
                                            min="0"
                                            required
                                            placeholder="0"
                                            class="h-8 w-24"
                                            :aria-label="`Stock of ${row.options.map((option, level) => optionLabel(option, row.positions[level])).join(', ')}`"
                                        />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <Input
                                            v-model="draft.variants[row.key].weight_grams"
                                            type="number"
                                            min="0"
                                            placeholder="Default"
                                            class="h-8 w-28"
                                            :aria-label="`Weight of ${row.options.map((option, level) => optionLabel(option, row.positions[level])).join(', ')}`"
                                        />
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-muted-foreground">
                    Leave weight empty to use the product weight. The product shows the lowest price, and its stock is the total of all variations.
                </p>
            </div>
        </template>

        <InputError :message="error" />
    </section>
</template>
