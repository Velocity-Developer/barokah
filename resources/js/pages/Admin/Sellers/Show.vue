<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, ImageOff, Mail, MapPin, MessageCircle, Pencil, Phone, Star, UserCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { show as orderShow } from '@/routes/admin/orders';
import { show as productShow } from '@/routes/admin/products';
import { index as sellerApprovalsIndex } from '@/routes/admin/seller-approvals';
import { edit, index } from '@/routes/admin/sellers';
import { formatPrice } from '@/services/priceFormatter';

type SellerProduct = { id: number; name: string; slug: string; price: string | number; stock: number; status: string; image: string | null };

type AdminSellerDetail = {
    id: number;
    store_name: string;
    slug: string;
    status: 'active' | 'pending' | 'suspended';
    description?: string | null;
    profile_photo_url?: string | null;
    banner_url?: string | null;
    display_photo_url?: string | null;
    display_banner_url?: string | null;
    phone?: string | null;
    whatsapp?: string | null;
    store_location?: string | null;
    bank_account?: string | null;
    state?: string | null;
    city?: string | null;
    created_at: string | null;
    owner: { id: number; name: string; email: string; phone?: string | null; is_active_as_seller: boolean } | null;
    products: SellerProduct[];
};

type RecentOrder = { order_number: string; customer_name: string; status: string; items: number; store_total_formatted: string; created_at: string | null };

const props = defineProps<{
    seller: AdminSellerDetail;
    public_url: string | null;
    stats: { revenue_formatted: string; units_sold: number; orders: number; followers: number; rating: number | null; reviews: number };
    recent_orders: RecentOrder[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Stores', href: index() }] } });

const isSaving = ref(false);

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    suspended: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
    draft: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    inactive: 'bg-muted text-muted-foreground ring-border',
    archived: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
    pending_payment: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    paid: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    processing: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    shipped: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-900',
    completed: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
};

function label(value: string): string {
    return value.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

/** Store descriptions are rich-text HTML; show plain paragraphs (no v-html). */
const descriptionParagraphs = computed<string[]>(() => {
    const html = props.seller.description ?? '';

    if (!html.trim()) return [];

    const doc = new DOMParser().parseFromString(html.replace(/<\/(p|h[1-6]|li|div)>|<br\s*\/?>/gi, '$&\n'), 'text/html');

    return (doc.body.textContent ?? '').split('\n').map((line) => line.trim()).filter(Boolean);
});

const whatsappUrl = computed(() => {
    const digits = (props.seller.whatsapp ?? '').replace(/\D/g, '');

    return digits ? `https://wa.me/${digits}` : null;
});

// The address often already ends with the city/state; only add what is missing.
const location = computed(() => {
    const address = props.seller.store_location ?? '';
    const extra = [props.seller.city, props.seller.state].filter((part): part is string => Boolean(part) && !address.toLowerCase().includes(String(part).toLowerCase()));

    return [address, ...extra].filter(Boolean).join(', ');
});

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

async function setStatus(status: 'active' | 'suspended'): Promise<void> {
    const question = status === 'suspended'
        ? `Suspend ${props.seller.store_name}? The store page and all its products are hidden from the storefront, and the owner loses access to the seller dashboard. Reactivating brings them back.`
        : `Reactivate ${props.seller.store_name}?`;

    if (!window.confirm(question)) return;

    isSaving.value = true;

    try {
        const response = await fetch(`/api/v1/admin/sellers/${props.seller.id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ status }),
        });

        if (!response.ok) throw new Error();

        toast.success(status === 'suspended' ? 'Store suspended.' : 'Store reactivated.');
        router.reload();
    } catch {
        toast.error('Store could not be updated.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head :title="seller.store_name" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Link :href="index()" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to stores
        </Link>

        <!-- Header -->
        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div
                class="h-32 bg-cover bg-center sm:h-44"
                :class="seller.display_banner_url ? '' : 'bg-gradient-to-r from-[var(--brand-primary,#ee4d2d)] to-[var(--accent-navy,#113366)]'"
                :style="seller.display_banner_url ? { backgroundImage: `url('${seller.display_banner_url}')` } : undefined"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-3 px-4 pb-4 md:flex-row md:items-end md:justify-between">
                <div class="flex items-end gap-3">
                    <img v-if="seller.display_photo_url" :src="seller.display_photo_url" alt="" class="-mt-10 size-20 rounded-full border-4 border-card object-cover" />
                    <span v-else class="-mt-10 flex size-20 items-center justify-center rounded-full border-4 border-card bg-[var(--accent-navy,#113366)] text-2xl font-semibold text-white" aria-hidden="true">
                        {{ seller.store_name.charAt(0).toUpperCase() }}
                    </span>
                    <div class="pb-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-semibold">{{ seller.store_name }}</h1>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[seller.status]">{{ label(seller.status) }}</span>
                        </div>
                        <p class="text-sm text-muted-foreground">/{{ seller.slug }} · joined {{ formatDate(seller.created_at) }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a v-if="public_url" :href="public_url" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <ExternalLink class="size-4" aria-hidden="true" /> View store
                    </a>
                    <Link v-if="seller.status === 'pending'" :href="sellerApprovalsIndex()" class="inline-flex h-9 items-center gap-1.5 rounded-md border border-amber-300 px-3 text-sm font-medium text-amber-800 hover:bg-amber-50 dark:text-amber-200">
                        <UserCheck class="size-4" aria-hidden="true" /> Review application
                    </Link>
                    <button v-else-if="seller.status === 'active'" type="button" :disabled="isSaving" class="inline-flex h-9 items-center rounded-md border border-red-200 px-3 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-400" @click="setStatus('suspended')">
                        Suspend
                    </button>
                    <button v-else type="button" :disabled="isSaving" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted disabled:opacity-50" @click="setStatus('active')">
                        Reactivate
                    </button>
                    <Link :href="edit(seller.id)" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                        <Pencil class="size-4" aria-hidden="true" /> Edit store
                    </Link>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Sales (paid orders)</p>
                <p class="mt-1 text-xl font-semibold">{{ stats.revenue_formatted }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">{{ stats.units_sold }} units in {{ stats.orders }} {{ stats.orders === 1 ? 'order' : 'orders' }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Products</p>
                <p class="mt-1 text-xl font-semibold">{{ seller.products.length }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">{{ seller.products.filter((product) => product.status === 'active').length }} active</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Rating</p>
                <p class="mt-1 flex items-center gap-1 text-xl font-semibold"><Star class="size-4 fill-amber-400 text-amber-400" aria-hidden="true" />{{ stats.rating ?? '—' }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">{{ stats.reviews }} {{ stats.reviews === 1 ? 'review' : 'reviews' }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Followers</p>
                <p class="mt-1 text-xl font-semibold">{{ stats.followers }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Customers following this store</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid content-start gap-4">
                <!-- Products -->
                <section class="rounded-xl border bg-card shadow-sm">
                    <div class="flex items-center justify-between gap-2 p-4 pb-2">
                        <h2 class="text-base font-medium">Products ({{ seller.products.length }})</h2>
                        <Link :href="`/admin/products?seller_id=${seller.id}`" class="text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">Manage in Products</Link>
                    </div>
                    <p v-if="!seller.products.length" class="p-4 pt-0 text-sm text-muted-foreground">No products yet.</p>
                    <div v-else class="max-h-[480px] overflow-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead class="sticky top-0 bg-card">
                                <tr class="border-b text-xs text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Product</th>
                                    <th class="px-4 py-2 text-right font-medium">Price</th>
                                    <th class="px-4 py-2 text-right font-medium">Stock</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in seller.products" :key="product.id" class="border-b last:border-0 hover:bg-muted/50">
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2.5">
                                            <img v-if="product.image" :src="product.image" alt="" class="size-8 shrink-0 rounded border object-cover" loading="lazy" />
                                            <span v-else class="flex size-8 shrink-0 items-center justify-center rounded border bg-muted text-muted-foreground" aria-hidden="true"><ImageOff class="size-3.5" /></span>
                                            <Link :href="productShow(product.id)" class="max-w-64 truncate font-medium hover:underline">{{ product.name }}</Link>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap tabular-nums">{{ formatPrice(Number(product.price)) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums" :class="product.stock <= 0 ? 'text-red-600' : product.stock <= 5 ? 'text-amber-700' : ''">{{ product.stock }}</td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[product.status] ?? 'bg-green-50 text-green-700 ring-green-200'">{{ label(product.status) }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Recent orders -->
                <section class="rounded-xl border bg-card shadow-sm">
                    <h2 class="p-4 pb-2 text-base font-medium">Recent orders</h2>
                    <p v-if="!recent_orders.length" class="p-4 pt-0 text-sm text-muted-foreground">No orders yet.</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead>
                                <tr class="border-b text-xs text-muted-foreground">
                                    <th class="px-4 py-2 font-medium">Order</th>
                                    <th class="px-4 py-2 font-medium">Customer</th>
                                    <th class="px-4 py-2 text-right font-medium">Items</th>
                                    <th class="px-4 py-2 text-right font-medium">Store total</th>
                                    <th class="px-4 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in recent_orders" :key="order.order_number" class="border-b last:border-0 hover:bg-muted/50">
                                    <td class="px-4 py-2">
                                        <Link :href="orderShow(order.order_number)" class="font-medium hover:underline">{{ order.order_number }}</Link>
                                        <p class="text-xs text-muted-foreground">{{ formatDate(order.created_at) }}</p>
                                    </td>
                                    <td class="px-4 py-2">{{ order.customer_name }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ order.items }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ order.store_total_formatted }}</td>
                                    <td class="px-4 py-2"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[order.status] ?? 'bg-muted text-muted-foreground ring-border'">{{ label(order.status) }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">About</h2>
                    <div v-if="descriptionParagraphs.length" class="space-y-1.5 text-sm leading-relaxed">
                        <p v-for="(paragraph, i) in descriptionParagraphs" :key="i">{{ paragraph }}</p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">No description.</p>
                    <ul class="grid content-start gap-2 border-t pt-3 text-sm">
                        <li v-if="location" class="flex gap-2"><MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />{{ location }}</li>
                        <li v-if="seller.phone" class="flex gap-2"><Phone class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" /><a :href="`tel:${seller.phone}`" class="hover:underline">{{ seller.phone }}</a></li>
                        <li v-if="seller.whatsapp" class="flex gap-2">
                            <MessageCircle class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ seller.whatsapp }}</a>
                        </li>
                    </ul>
                </section>

                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Payout account</h2>
                    <p class="text-sm">{{ seller.bank_account || 'Not set yet' }}</p>
                    <p class="text-xs text-muted-foreground">Only admins and the store owner can see this.</p>
                </section>

                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Owner</h2>
                    <template v-if="seller.owner">
                        <p class="text-sm font-medium">{{ seller.owner.name }}</p>
                        <p class="flex items-center gap-2 text-sm"><Mail class="size-4 text-muted-foreground" aria-hidden="true" /><a :href="`mailto:${seller.owner.email}`" class="hover:underline">{{ seller.owner.email }}</a></p>
                        <p v-if="seller.owner.phone" class="flex items-center gap-2 text-sm"><Phone class="size-4 text-muted-foreground" aria-hidden="true" />{{ seller.owner.phone }}</p>
                        <Link :href="`/admin/users/${seller.owner.id}`" class="text-sm font-medium text-[var(--brand-primary,#ee4d2d)] hover:underline">View customer account</Link>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">No owner linked.</p>
                </section>
            </aside>
        </div>
    </div>
</template>
