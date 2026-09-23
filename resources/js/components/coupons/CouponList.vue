<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Copy, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';

type CouponRow = {
    id: number;
    code: string;
    name: string;
    owner: string | null;
    discount_label: string;
    minimum_spend_formatted: string | null;
    usage_count: number;
    usage_limit: number | null;
    starts_at: string;
    ends_at: string;
    enabled: boolean;
    status: 'active' | 'scheduled' | 'used_up' | 'expired' | 'disabled';
};

type Paginated = {
    data: CouponRow[];
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    coupons: Paginated;
    filters: { search: string; status: string; owner: string; sort: string };
    statusCounts: Record<string, number>;
    /** Admins see every coupon and can scope them; sellers see their own. */
    mode?: 'admin' | 'seller';
}>();

const isAdmin = computed(() => (props.mode ?? 'admin') === 'admin');
const base = computed(() => (isAdmin.value ? '/admin/coupons' : '/seller/coupons'));
const apiBase = computed(() => (isAdmin.value ? '/api/v1/admin/coupons' : '/api/v1/seller/coupons'));

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'used_up', label: 'Used up' },
    { value: 'expired', label: 'Expired' },
    { value: 'disabled', label: 'Disabled' },
];

const SORTS = [
    { value: 'newest', label: 'Newest first' },
    { value: 'ends_asc', label: 'Ending soonest' },
    { value: 'most_used', label: 'Most used' },
    { value: 'code_asc', label: 'Code A–Z' },
];

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    scheduled: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    used_up: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    expired: 'bg-muted text-muted-foreground ring-border',
    disabled: 'bg-muted text-muted-foreground ring-border',
};

const search = ref(props.filters.search);
const busyId = ref<number | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;

function visit(overrides: Partial<typeof props.filters> = {}): void {
    const query = { ...props.filters, search: search.value.trim(), ...overrides };
    const clean = Object.fromEntries(Object.entries(query).filter(([key, value]) => value !== '' && !(key === 'sort' && value === 'newest')));

    router.get(base.value, clean, { preserveState: true, preserveScroll: true, replace: true });
}

watch(search, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => visit(), 350);
});

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});

function label(status: string): string {
    return STATUS_TABS.find((tab) => tab.value === status)?.label ?? status;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function usagePercent(coupon: CouponRow): number | null {
    return coupon.usage_limit ? Math.min(100, Math.round((coupon.usage_count / coupon.usage_limit) * 100)) : null;
}

async function copyCode(code: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(code);
        toast.success(`Copied ${code}`);
    } catch {
        toast.error('Could not copy the code.');
    }
}

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

async function remove(coupon: CouponRow): Promise<void> {
    if (!window.confirm(`Delete coupon ${coupon.code}? This cannot be undone.`)) return;

    busyId.value = coupon.id;

    try {
        const response = await fetch(`${apiBase.value}/${coupon.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });

        if (!response.ok) {
            const payload = (await response.json().catch(() => ({}))) as { message?: string };
            toast.error(payload.message ?? 'Coupon could not be deleted.');
            return;
        }

        toast.success(`${coupon.code} deleted.`);
        router.reload({ only: ['coupons', 'statusCounts'] });
    } catch {
        toast.error('Coupon could not be deleted.');
    } finally {
        busyId.value = null;
    }
}

const total = () => Object.values(props.statusCounts).reduce((sum, count) => sum + count, 0);
const selectClass = 'h-9 rounded-md border bg-background px-2.5 text-sm';
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                variant="small"
                title="Coupons"
                :description="isAdmin ? 'Discount codes customers enter at checkout, for the whole marketplace or one store.' : 'Discount codes customers enter at checkout. Yours apply to your store only.'"
            />
            <Link :href="`${base}/create`" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                <Plus class="size-4" aria-hidden="true" /> New coupon
            </Link>
        </div>

        <section class="rounded-xl border bg-card shadow-sm">
            <nav class="flex flex-wrap gap-x-1 border-b px-3 pt-2" aria-label="Coupon status">
                <button
                    v-for="tab in STATUS_TABS"
                    :key="tab.value"
                    type="button"
                    class="-mb-px flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition"
                    :class="filters.status === tab.value ? 'border-[var(--brand-primary,#ee4d2d)] font-medium text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    :aria-pressed="filters.status === tab.value"
                    @click="visit({ status: tab.value })"
                >
                    {{ tab.label }}
                    <span class="rounded-full bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">
                        {{ tab.value === '' ? total() : (statusCounts[tab.value] ?? 0) }}
                    </span>
                </button>
            </nav>

            <div class="flex flex-col gap-2 p-3 lg:flex-row lg:items-center">
                <label class="relative flex-1">
                    <span class="sr-only">Search coupons</span>
                    <Search class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input v-model="search" type="search" :placeholder="isAdmin ? 'Search code, name or store' : 'Search code or name'" class="h-9 w-full rounded-md border bg-background pr-3 pl-8 text-sm" />
                </label>
                <div class="flex flex-wrap gap-2">
                    <select v-if="isAdmin" :value="filters.owner" :class="selectClass" aria-label="Scope" @change="visit({ owner: ($event.target as HTMLSelectElement).value })">
                        <option value="">All scopes</option>
                        <option value="global">Marketplace-wide</option>
                        <option value="seller">Store coupons</option>
                    </select>
                    <select :value="filters.sort" :class="selectClass" aria-label="Sort" @change="visit({ sort: ($event.target as HTMLSelectElement).value })">
                        <option v-for="sort in SORTS" :key="sort.value" :value="sort.value">{{ sort.label }}</option>
                    </select>
                </div>
            </div>

            <div class="border-t">
                <div v-if="coupons.data.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    <template v-if="filters.search || filters.status || (isAdmin && filters.owner)">No coupons match these filters.</template>
                    <template v-else>No coupons yet. Create one to reward customers at checkout.</template>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Coupon</th>
                                <th class="px-4 py-2 font-medium">Discount</th>
                                <th v-if="isAdmin" class="px-4 py-2 font-medium">Scope</th>
                                <th class="px-4 py-2 font-medium">Used</th>
                                <th class="px-4 py-2 font-medium">Valid</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="coupon in coupons.data" :key="coupon.id" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded border border-dashed border-[var(--brand-primary,#ee4d2d)] px-1.5 py-0.5 font-mono text-xs font-semibold tracking-wide text-[var(--brand-primary,#ee4d2d)]">{{ coupon.code }}</span>
                                        <button type="button" class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`Copy ${coupon.code}`" @click="copyCode(coupon.code)">
                                            <Copy class="size-3.5" />
                                        </button>
                                    </div>
                                    <p class="mt-0.5 max-w-56 truncate text-xs text-muted-foreground">{{ coupon.name }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <p class="font-medium">{{ coupon.discount_label }}</p>
                                    <p class="text-xs text-muted-foreground">{{ coupon.minimum_spend_formatted ? `Min. spend ${coupon.minimum_spend_formatted}` : 'No minimum spend' }}</p>
                                </td>
                                <td v-if="isAdmin" class="px-4 py-2.5">{{ coupon.owner ?? 'Marketplace-wide' }}</td>
                                <td class="w-36 px-4 py-2.5">
                                    <p class="text-xs tabular-nums">{{ coupon.usage_count }}{{ coupon.usage_limit ? ` / ${coupon.usage_limit}` : ' · unlimited' }}</p>
                                    <div v-if="usagePercent(coupon) !== null" class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                        <div class="h-full rounded-full bg-[var(--brand-primary,#ee4d2d)]" :style="{ width: `${usagePercent(coupon)}%` }" />
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-xs whitespace-nowrap">
                                    <p>{{ formatDate(coupon.starts_at) }}</p>
                                    <p class="text-muted-foreground">to {{ formatDate(coupon.ends_at) }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset" :class="statusStyles[coupon.status]">{{ label(coupon.status) }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="`${base}/${coupon.id}/edit`" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground" :aria-label="`Edit ${coupon.code}`">
                                            <Pencil class="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-muted-foreground"
                                            :disabled="busyId === coupon.id || coupon.usage_count > 0"
                                            :title="coupon.usage_count > 0 ? 'Used coupons cannot be deleted — disable it instead' : `Delete ${coupon.code}`"
                                            :aria-label="`Delete ${coupon.code}`"
                                            @click="remove(coupon)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="coupons.total > 0" class="flex flex-col gap-3 border-t p-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">
                        Showing <span class="font-medium text-foreground">{{ coupons.from }}–{{ coupons.to }}</span> of
                        <span class="font-medium text-foreground">{{ coupons.total }}</span> coupons
                    </p>
                    <nav v-if="coupons.last_page > 1" class="flex flex-wrap gap-1" aria-label="Pagination">
                        <template v-for="(link, i) in coupons.links" :key="i">
                            <Link
                                v-if="link.url && /^\d+$/.test(link.label)"
                                :href="link.url"
                                preserve-scroll
                                class="min-w-8 rounded border px-2.5 py-1 text-center text-xs"
                                :class="link.active ? 'border-[var(--brand-primary,#ee4d2d)] bg-[var(--brand-primary,#ee4d2d)] text-white' : 'hover:bg-muted'"
                            >
                                {{ link.label }}
                            </Link>
                        </template>
                    </nav>
                </div>
            </div>
        </section>
    </div>
</template>
