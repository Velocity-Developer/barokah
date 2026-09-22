<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/flash-sales';
import { useSettingsStore } from '@/stores/settings';

export type FlashSaleProduct = { id: number; name: string; price: number; stock: number; store: string | null };

export type FlashSaleValue = {
    id: number;
    discount_type: 'percentage' | 'fixed';
    discount_value: number;
    quantity: number;
    quantity_sold: number;
    starts_at: string;
    ends_at: string;
    status: string;
    product: FlashSaleProduct;
};

const props = defineProps<{
    products?: FlashSaleProduct[];
    flashSale?: FlashSaleValue | null;
}>();

const { formatAmount, currencySymbol } = useSettingsStore();
const isEdit = computed(() => Boolean(props.flashSale));

/** <input type="datetime-local"> works in the browser's local time; the API gets full ISO timestamps. */
function toLocalInput(value: Date | string): string {
    const date = new Date(value);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function inDays(days: number, from = new Date()): Date {
    return new Date(from.getTime() + days * 86_400_000);
}

const now = new Date();
now.setSeconds(0, 0);

const form = reactive({
    product_id: props.flashSale ? String(props.flashSale.product.id) : '',
    discount_type: props.flashSale?.discount_type ?? ('percentage' as 'percentage' | 'fixed'),
    discount_value: props.flashSale ? String(props.flashSale.discount_value) : '',
    quantity: props.flashSale ? String(props.flashSale.quantity) : '',
    starts_at: toLocalInput(props.flashSale?.starts_at ?? now),
    ends_at: toLocalInput(props.flashSale?.ends_at ?? inDays(3, now)),
});

const productSearch = ref('');
const errors = ref<Record<string, string>>({});
const isSaving = ref(false);
const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const filteredProducts = computed(() => {
    const term = productSearch.value.trim().toLowerCase();
    const list = props.products ?? [];

    return term === '' ? list : list.filter((product) => `${product.name} ${product.store ?? ''}`.toLowerCase().includes(term));
});

const product = computed<FlashSaleProduct | null>(() =>
    props.flashSale?.product ?? props.products?.find((item) => String(item.id) === form.product_id) ?? null,
);

const maxQuantity = computed(() => (product.value ? product.value.stock + (props.flashSale?.quantity_sold ?? 0) : null));

const promoPrice = computed<number | null>(() => {
    const value = Number(form.discount_value);

    if (!product.value || form.discount_value === '' || !Number.isFinite(value)) return null;

    return form.discount_type === 'fixed' ? value : Math.round(product.value.price * (1 - value / 100) * 100) / 100;
});

const previewProblem = computed<string | null>(() => {
    if (!product.value || promoPrice.value === null) return null;
    if (form.discount_type === 'percentage' && Number(form.discount_value) > 100) return 'A percentage discount cannot be more than 100%.';
    if (promoPrice.value <= 0) return 'The promo price must be above zero.';
    if (promoPrice.value >= product.value.price) return `The promo price must be lower than the normal price (${formatAmount(product.value.price)}).`;

    return null;
});

const savingPercent = computed(() =>
    product.value && promoPrice.value !== null && product.value.price > 0 ? Math.round((1 - promoPrice.value / product.value.price) * 100) : null,
);

function setDuration(days: number): void {
    form.ends_at = toLocalInput(inDays(days, new Date(form.starts_at)));
}

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

async function save(): Promise<void> {
    if (previewProblem.value) {
        errors.value = { discount_value: previewProblem.value };
        return;
    }

    isSaving.value = true;
    errors.value = {};

    const payload = {
        discount_type: form.discount_type,
        discount_value: Number(form.discount_value),
        quantity: Number(form.quantity),
        starts_at: new Date(form.starts_at).toISOString(),
        ends_at: new Date(form.ends_at).toISOString(),
    };
    const url = isEdit.value ? `/api/v1/admin/flash-sales/${props.flashSale!.id}` : `/api/v1/admin/products/${form.product_id}/flash-sale`;

    try {
        const response = await fetch(url, {
            method: isEdit.value ? 'PUT' : 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const body = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
            errors.value = Object.fromEntries(Object.entries(body.errors ?? {}).map(([field, messages]) => [field, messages[0] ?? 'Invalid value.']));
            toast.error(body.message ?? 'Flash sale could not be saved.');
            return;
        }

        toast.success(isEdit.value ? 'Flash sale updated.' : 'Flash sale created.');
        router.visit(index().url);
    } catch {
        toast.error('Flash sales are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <Link :href="index()" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to flash sales
        </Link>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading
                variant="small"
                :title="isEdit ? 'Edit flash sale' : 'New flash sale'"
                :description="isEdit ? `${flashSale!.product.name}${flashSale!.product.store ? ' · ' + flashSale!.product.store : ''}` : 'Give a product a lower price for a limited time and quantity.'"
            />
            <div class="flex shrink-0 gap-2">
                <Link :href="index()" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                <Button type="submit" :disabled="isSaving || (!isEdit && !form.product_id)">
                    {{ isSaving ? 'Saving…' : isEdit ? 'Save changes' : 'Create flash sale' }}
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="grid content-start gap-4">
                <!-- Product -->
                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Product</h2>
                    <template v-if="!isEdit">
                        <Input v-model="productSearch" type="search" placeholder="Search product or store" aria-label="Search products" />
                        <select v-model="form.product_id" required size="6" class="w-full rounded-md border border-input bg-transparent p-1 text-sm" aria-label="Product">
                            <option v-for="item in filteredProducts" :key="item.id" :value="String(item.id)" class="rounded px-2 py-1.5">
                                {{ item.name }} — {{ item.store ?? 'No store' }} · {{ formatAmount(item.price) }} · stock {{ item.stock }}
                            </option>
                        </select>
                        <p v-if="!filteredProducts.length" class="text-xs text-muted-foreground">No active products with stock match your search.</p>
                        <InputError :message="errors.product_id" />
                    </template>
                    <div v-if="product" class="flex flex-wrap gap-x-6 gap-y-1 rounded-md bg-muted/50 px-3 py-2 text-sm">
                        <span><span class="text-muted-foreground">Selected:</span> <span class="font-medium">{{ product.name }}</span></span>
                        <span><span class="text-muted-foreground">Normal price:</span> {{ formatAmount(product.price) }}</span>
                        <span><span class="text-muted-foreground">In stock:</span> {{ product.stock }}</span>
                    </div>
                </section>

                <!-- Discount -->
                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Discount</h2>
                    <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Discount type">
                        <label
                            v-for="option in [
                                { value: 'percentage', title: 'Percentage off', hint: 'e.g. 20% off the normal price' },
                                { value: 'fixed', title: 'Fixed promo price', hint: 'Set the exact sale price' },
                            ]"
                            :key="option.value"
                            class="flex cursor-pointer items-start gap-2 rounded-md border p-3 text-sm transition"
                            :class="form.discount_type === option.value ? 'border-[var(--brand-primary,#ee4d2d)] bg-[var(--brand-primary,#ee4d2d)]/5' : 'hover:bg-muted/50'"
                        >
                            <input v-model="form.discount_type" type="radio" :value="option.value" class="mt-0.5 accent-[var(--brand-primary,#ee4d2d)]" />
                            <span>
                                <span class="font-medium">{{ option.title }}</span>
                                <span class="block text-xs text-muted-foreground">{{ option.hint }}</span>
                            </span>
                        </label>
                    </div>
                    <div class="grid content-start gap-2 sm:max-w-xs">
                        <Label for="discount_value">{{ form.discount_type === 'percentage' ? 'Discount (%)' : 'Promo price' }}</Label>
                        <div class="relative">
                            <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                {{ form.discount_type === 'percentage' ? '%' : currencySymbol() }}
                            </span>
                            <Input
                                id="discount_value"
                                v-model="form.discount_value"
                                type="number"
                                required
                                min="0"
                                :max="form.discount_type === 'percentage' ? 100 : undefined"
                                step="0.01"
                                class="pl-10"
                            />
                        </div>
                        <InputError :message="errors.discount_value ?? previewProblem ?? undefined" />
                    </div>
                </section>

                <!-- Schedule -->
                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-base font-medium">Schedule &amp; quota</h2>
                        <p class="text-xs text-muted-foreground">Times are in your local time ({{ timeZone }}).</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="starts_at">Starts</Label>
                            <Input id="starts_at" v-model="form.starts_at" type="datetime-local" required :disabled="isEdit && flashSale!.status !== 'scheduled'" />
                            <InputError :message="errors.starts_at" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="ends_at">Ends</Label>
                            <Input id="ends_at" v-model="form.ends_at" type="datetime-local" required :min="form.starts_at" />
                            <InputError :message="errors.ends_at" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-muted-foreground">Duration:</span>
                        <button v-for="days in [1, 3, 7, 14]" :key="days" type="button" class="rounded-full border px-2.5 py-0.5 hover:bg-muted" @click="setDuration(days)">
                            {{ days }} {{ days === 1 ? 'day' : 'days' }}
                        </button>
                    </div>
                    <div class="grid content-start gap-2 sm:max-w-xs">
                        <Label for="quantity">Quota (units at the promo price)</Label>
                        <Input id="quantity" v-model="form.quantity" type="number" required min="1" :max="maxQuantity ?? undefined" />
                        <p v-if="maxQuantity !== null" class="text-xs text-muted-foreground">
                            Up to {{ maxQuantity }}<template v-if="isEdit && flashSale!.quantity_sold"> ({{ flashSale!.quantity_sold }} already sold)</template>.
                        </p>
                        <InputError :message="errors.quantity" />
                    </div>
                </section>
            </div>

            <!-- Preview -->
            <aside class="grid content-start gap-4">
                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm lg:sticky lg:top-4">
                    <h2 class="text-base font-medium">Preview</h2>
                    <template v-if="product && promoPrice !== null && !previewProblem">
                        <p class="text-2xl font-semibold text-[var(--brand-primary,#ee4d2d)]">{{ formatAmount(promoPrice) }}</p>
                        <p class="text-sm text-muted-foreground">
                            <span class="line-through">{{ formatAmount(product.price) }}</span>
                            <span v-if="savingPercent !== null" class="ml-1 font-medium text-foreground">{{ savingPercent }}% off</span>
                        </p>
                        <p class="text-xs text-muted-foreground">Customers save {{ formatAmount(product.price - promoPrice) }} per unit.</p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">Choose a product and a discount to see the promo price.</p>
                </section>
            </aside>
        </div>
    </form>
</template>
