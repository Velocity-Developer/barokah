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
import { useSettingsStore } from '@/stores/settings';

type DiscountType = 'percentage' | 'fixed' | 'free_shipping';

export type CouponValue = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    owner_type: 'global' | 'seller';
    seller_id: number | null;
    discount_type: DiscountType;
    discount_value: number;
    maximum_discount: number | null;
    minimum_spend: number;
    usage_limit: number | null;
    usage_count: number;
    per_user_limit: number | null;
    allow_flash_sale: boolean;
    status: boolean;
    starts_at: string;
    ends_at: string;
};

const props = defineProps<{
    coupon?: CouponValue | null;
    /** Admins choose the scope; a seller's coupon always belongs to their store. */
    sellers?: { id: number; store_name: string }[];
    mode?: 'admin' | 'seller';
}>();

const isAdmin = computed(() => (props.mode ?? 'admin') === 'admin');
const base = computed(() => (isAdmin.value ? '/admin/coupons' : '/seller/coupons'));
const apiBase = computed(() => (isAdmin.value ? '/api/v1/admin/coupons' : '/api/v1/seller/coupons'));

const { formatAmount, currencySymbol } = useSettingsStore();
const isEdit = computed(() => Boolean(props.coupon));
const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

/** <input type="datetime-local"> is local time; the API receives full ISO timestamps. */
function toLocalInput(value: Date | string): string {
    const date = new Date(value);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const now = new Date();
now.setSeconds(0, 0);

const form = reactive({
    code: props.coupon?.code ?? '',
    name: props.coupon?.name ?? '',
    description: props.coupon?.description ?? '',
    // A seller's coupon is always their own store's; the API enforces it too.
    owner_type: props.coupon?.owner_type ?? (((props.mode ?? 'admin') === 'seller' ? 'seller' : 'global') as 'global' | 'seller'),
    seller_id: props.coupon?.seller_id ? String(props.coupon.seller_id) : '',
    discount_type: props.coupon?.discount_type ?? ('percentage' as DiscountType),
    discount_value: props.coupon ? String(props.coupon.discount_value) : '',
    maximum_discount: props.coupon?.maximum_discount != null ? String(props.coupon.maximum_discount) : '',
    minimum_spend: props.coupon ? String(props.coupon.minimum_spend) : '0',
    usage_limit: props.coupon?.usage_limit != null ? String(props.coupon.usage_limit) : '',
    per_user_limit: props.coupon?.per_user_limit != null ? String(props.coupon.per_user_limit) : '',
    starts_at: toLocalInput(props.coupon?.starts_at ?? now),
    ends_at: toLocalInput(props.coupon?.ends_at ?? new Date(now.getTime() + 30 * 86_400_000)),
    allow_flash_sale: props.coupon?.allow_flash_sale ?? false,
    status: props.coupon?.status ?? true,
});

const errors = ref<Record<string, string>>({});
const isSaving = ref(false);

const summary = computed(() => {
    const value = Number(form.discount_value) || 0;
    const parts: string[] = [];

    if (form.discount_type === 'percentage') {
        parts.push(`${value}% off the order`);
        if (Number(form.maximum_discount) > 0) parts.push(`up to ${formatAmount(Number(form.maximum_discount))}`);
    } else if (form.discount_type === 'fixed') {
        parts.push(`${formatAmount(value)} off the order`);
    } else {
        parts.push('Free shipping');
    }

    if (Number(form.minimum_spend) > 0) parts.push(`on orders of ${formatAmount(Number(form.minimum_spend))} or more`);

    if (! isAdmin.value) {
        parts.push('for products from your store');
    } else {
        const store = props.sellers?.find((seller) => String(seller.id) === form.seller_id)?.store_name;
        parts.push(form.owner_type === 'seller' ? `for products from ${store ?? 'one store'}` : 'across the marketplace');
    }

    return parts.join(' ');
});

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function nullable(value: string): number | null {
    return value === '' ? null : Number(value);
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    const payload = {
        code: form.code.trim().toUpperCase(),
        name: form.name.trim(),
        description: form.description.trim() || null,
        owner_type: form.owner_type,
        seller_id: form.owner_type === 'seller' ? nullable(form.seller_id) : null,
        discount_type: form.discount_type,
        // Free shipping always waives the whole shipping fee; the value is not used.
        discount_value: form.discount_type === 'free_shipping' ? 0 : Number(form.discount_value || 0),
        maximum_discount: form.discount_type === 'percentage' ? nullable(form.maximum_discount) : null,
        minimum_spend: Number(form.minimum_spend || 0),
        usage_limit: nullable(form.usage_limit),
        per_user_limit: nullable(form.per_user_limit),
        starts_at: new Date(form.starts_at).toISOString(),
        ends_at: new Date(form.ends_at).toISOString(),
        allow_flash_sale: form.allow_flash_sale,
        status: form.status,
    };

    try {
        const response = await fetch(isEdit.value ? `${apiBase.value}/${props.coupon!.id}` : apiBase.value, {
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
            toast.error(body.message ?? 'Coupon could not be saved.');
            return;
        }

        toast.success(isEdit.value ? 'Coupon updated.' : 'Coupon created.');
        router.visit(base.value);
    } catch {
        toast.error('Coupons are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <Link :href="base" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to coupons
        </Link>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading
                variant="small"
                :title="isEdit ? `Edit ${coupon!.code}` : 'New coupon'"
                :description="isEdit ? `Used ${coupon!.usage_count} ${coupon!.usage_count === 1 ? 'time' : 'times'}` : 'Customers enter the code at checkout to get the discount.'"
            />
            <div class="flex shrink-0 gap-2">
                <Link :href="base" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                <Button type="submit" :disabled="isSaving">{{ isSaving ? 'Saving…' : isEdit ? 'Save changes' : 'Create coupon' }}</Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="grid content-start gap-4">
                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Coupon</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="code">Code</Label>
                            <Input id="code" v-model="form.code" required maxlength="50" class="font-mono uppercase" placeholder="e.g. RAYA20" @input="form.code = form.code.toUpperCase().replace(/\s+/g, '')" />
                            <InputError :message="errors.code" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" required maxlength="255" placeholder="e.g. Raya sale 20%" />
                            <InputError :message="errors.name" />
                        </div>
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="description">Description <span class="font-normal text-muted-foreground">(shown to customers, optional)</span></Label>
                        <textarea id="description" v-model="form.description" rows="2" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                        <InputError :message="errors.description" />
                    </div>
                </section>

                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Discount</h2>
                    <div class="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Discount type">
                        <label
                            v-for="option in [
                                { value: 'percentage', title: 'Percentage', hint: '% off the order' },
                                { value: 'fixed', title: 'Fixed amount', hint: `${currencySymbol()} off the order` },
                                { value: 'free_shipping', title: 'Free shipping', hint: 'Waives the shipping fee' },
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
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-if="form.discount_type !== 'free_shipping'" class="grid content-start gap-2">
                            <Label for="discount_value">{{ form.discount_type === 'percentage' ? 'Discount (%)' : `Amount off (${currencySymbol()})` }}</Label>
                            <Input id="discount_value" v-model="form.discount_value" type="number" required min="0" :max="form.discount_type === 'percentage' ? 100 : undefined" step="0.01" />
                            <InputError :message="errors.discount_value" />
                        </div>
                        <div v-if="form.discount_type === 'percentage'" class="grid content-start gap-2">
                            <Label for="maximum_discount">Maximum discount ({{ currencySymbol() }})</Label>
                            <Input id="maximum_discount" v-model="form.maximum_discount" type="number" min="0" step="0.01" placeholder="No cap" />
                            <InputError :message="errors.maximum_discount" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="minimum_spend">Minimum spend ({{ currencySymbol() }})</Label>
                            <Input id="minimum_spend" v-model="form.minimum_spend" type="number" min="0" step="0.01" />
                            <InputError :message="errors.minimum_spend" />
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-start gap-2 text-sm">
                        <input v-model="form.allow_flash_sale" type="checkbox" class="mt-0.5 accent-[var(--brand-primary,#ee4d2d)]" />
                        <span>
                            Also applies to flash sale items
                            <span class="block text-xs text-muted-foreground">When off, products already on flash sale are excluded from the discount.</span>
                        </span>
                    </label>
                </section>

                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-base font-medium">Validity &amp; limits</h2>
                        <p class="text-xs text-muted-foreground">Times are in your local time ({{ timeZone }}).</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="starts_at">Starts</Label>
                            <Input id="starts_at" v-model="form.starts_at" type="datetime-local" required />
                            <InputError :message="errors.starts_at" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="ends_at">Ends</Label>
                            <Input id="ends_at" v-model="form.ends_at" type="datetime-local" required :min="form.starts_at" />
                            <InputError :message="errors.ends_at" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="usage_limit">Total uses</Label>
                            <Input id="usage_limit" v-model="form.usage_limit" type="number" min="1" placeholder="Unlimited" />
                            <InputError :message="errors.usage_limit" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="per_user_limit">Uses per customer</Label>
                            <Input id="per_user_limit" v-model="form.per_user_limit" type="number" min="1" placeholder="Unlimited" />
                            <InputError :message="errors.per_user_limit" />
                        </div>
                    </div>
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Status</h2>
                    <label class="flex cursor-pointer items-start gap-2 text-sm">
                        <input v-model="form.status" type="checkbox" class="mt-0.5 accent-[var(--brand-primary,#ee4d2d)]" />
                        <span>
                            <span class="font-medium">Enabled</span>
                            <span class="block text-xs text-muted-foreground">Disabled coupons cannot be used even inside their dates.</span>
                        </span>
                    </label>
                </section>

                <section v-if="isAdmin" class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Scope</h2>
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input v-model="form.owner_type" type="radio" value="global" class="accent-[var(--brand-primary,#ee4d2d)]" />
                        Marketplace-wide
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <input v-model="form.owner_type" type="radio" value="seller" class="accent-[var(--brand-primary,#ee4d2d)]" />
                        One store only
                    </label>
                    <select v-if="form.owner_type === 'seller'" v-model="form.seller_id" required class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm" aria-label="Store">
                        <option value="" disabled>Choose a store</option>
                        <option v-for="seller in sellers" :key="seller.id" :value="String(seller.id)">{{ seller.store_name }}</option>
                    </select>
                    <InputError :message="errors.seller_id ?? errors.owner_type" />
                </section>

                <section class="grid content-start gap-1 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Summary</h2>
                    <p class="font-mono text-sm font-semibold text-[var(--brand-primary,#ee4d2d)]">{{ form.code || 'CODE' }}</p>
                    <p class="text-sm">{{ summary }}.</p>
                </section>
            </aside>
        </div>
    </form>
</template>
