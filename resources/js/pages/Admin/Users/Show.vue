<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Heart, Mail, MapPin, Pencil, Phone, ShoppingBag, Store, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import { htmlToParagraphs } from '@/lib/richText';
import { edit, index } from '@/routes/admin/users';
import { show as orderShow } from '@/routes/admin/orders';
import { show as sellerShow } from '@/routes/admin/sellers';

type AdminUserDetail = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    address?: string | null;
    state?: string | null;
    city?: string | null;
    post_code?: string | null;
    profile_photo_url?: string | null;
    banner_url?: string | null;
    email_verified_at?: string | null;
    is_admin: boolean;
    is_active_as_seller: boolean;
    is_seller?: boolean;
    joined_at?: string | null;
    updated_at?: string | null;
    seller?: { id: number; store_name: string; slug: string; status: string; products_count?: number } | null;
};

const props = defineProps<{
    user: AdminUserDetail;
    stats: { orders: number; spent_formatted: string; followed_stores: number; favorite_products: number };
    recent_orders: { order_number: string; status: string; total_formatted: string; created_at: string | null }[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Customers', href: index() }] } });

const addressParagraphs = computed(() => htmlToParagraphs(props.user.address));

// The address often already names the city / state; only add what is missing.
const region = computed(() => {
    const written = addressParagraphs.value.join(' ').toLowerCase();

    return [props.user.city, props.user.state, props.user.post_code]
        .filter((part): part is string => Boolean(part) && !written.includes(String(part).toLowerCase()))
        .join(', ');
});

const storeStatusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    suspended: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
};

const orderStatusStyles: Record<string, string> = {
    pending_payment: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    paid: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    processing: 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-900',
    shipped: 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-900',
    completed: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    cancelled: 'bg-muted text-muted-foreground ring-border',
    expired: 'bg-muted text-muted-foreground ring-border',
};

function label(value: string): string {
    return value.replaceAll('_', ' ').replace(/^\w/, (letter) => letter.toUpperCase());
}

function formatDate(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleDateString('en-GB', { dateStyle: 'medium' }) : '—';
}

function formatDateTime(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}
</script>

<template>
    <Head :title="user.name" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <Link :href="index()" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to customers
        </Link>

        <!-- Header -->
        <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div
                class="h-24 bg-cover bg-center sm:h-32"
                :class="user.banner_url ? '' : 'bg-gradient-to-r from-[var(--brand-primary,#ee4d2d)] to-[var(--accent-navy,#113366)]'"
                :style="user.banner_url ? { backgroundImage: `url('${user.banner_url}')` } : undefined"
                aria-hidden="true"
            />
            <div class="flex flex-col gap-3 px-4 pb-4 md:flex-row md:items-end md:justify-between">
                <div class="flex items-end gap-3">
                    <img v-if="user.profile_photo_url" :src="user.profile_photo_url" alt="" class="-mt-10 size-20 rounded-full border-4 border-card object-cover" />
                    <span v-else class="-mt-10 flex size-20 items-center justify-center rounded-full border-4 border-card bg-[var(--accent-navy,#113366)] text-2xl font-semibold text-white" aria-hidden="true">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </span>
                    <div class="pb-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-semibold">{{ user.name }}</h1>
                            <span v-if="user.is_admin" class="inline-flex rounded-full bg-[var(--accent-navy,#113366)] px-2 py-0.5 text-xs font-medium text-white">Admin</span>
                            <span v-if="user.seller" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="storeStatusStyles[user.seller.status] ?? 'bg-muted text-muted-foreground ring-border'">
                                Store {{ user.seller.status }}
                            </span>
                            <span v-if="!user.email_verified_at" class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200 ring-inset dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-900">
                                Email unverified
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ user.email }} · joined {{ formatDate(user.joined_at) }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="`mailto:${user.email}`" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <Mail class="size-4" aria-hidden="true" /> Email
                    </a>
                    <Link v-if="user.seller" :href="sellerShow(user.seller.id)" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                        <Store class="size-4" aria-hidden="true" /> View store
                    </Link>
                    <Link :href="edit(user.id)" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90">
                        <Pencil class="size-4" aria-hidden="true" /> Edit customer
                    </Link>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><ShoppingBag class="size-4" aria-hidden="true" /> Orders</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.orders }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><Wallet class="size-4" aria-hidden="true" /> Spent (paid orders)</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.spent_formatted }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><Store class="size-4" aria-hidden="true" /> Followed stores</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.followed_stores }}</p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <p class="flex items-center gap-1.5 text-sm text-muted-foreground"><Heart class="size-4" aria-hidden="true" /> Favorite products</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ stats.favorite_products }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <!-- Recent orders -->
            <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <h2 class="border-b px-4 py-3 text-base font-medium">Recent orders</h2>
                <p v-if="recent_orders.length === 0" class="p-6 text-center text-sm text-muted-foreground">This customer has not ordered yet.</p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="px-4 py-2 font-medium">Order</th>
                                <th class="px-4 py-2 font-medium">Placed</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in recent_orders" :key="order.order_number" class="border-b last:border-0 hover:bg-muted/50">
                                <td class="px-4 py-2.5">
                                    <Link :href="orderShow(order.order_number)" class="font-medium hover:underline">{{ order.order_number }}</Link>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-muted-foreground">{{ formatDateTime(order.created_at) }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="orderStatusStyles[order.status] ?? 'bg-muted text-muted-foreground ring-border'">
                                        {{ label(order.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ order.total_formatted }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid content-start gap-4">
                <!-- Contact -->
                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Contact &amp; delivery</h2>
                    <p class="flex items-start gap-2 text-sm">
                        <Mail class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <a :href="`mailto:${user.email}`" class="break-all hover:underline">{{ user.email }}</a>
                    </p>
                    <p class="flex items-start gap-2 text-sm">
                        <Phone class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <span>{{ user.phone ?? 'No phone number' }}</span>
                    </p>
                    <div class="flex items-start gap-2 text-sm">
                        <MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <div class="grid gap-0.5">
                            <template v-if="addressParagraphs.length">
                                <span v-for="(line, i) in addressParagraphs" :key="i">{{ line }}</span>
                            </template>
                            <span v-else class="text-muted-foreground">No delivery address</span>
                            <span v-if="region" class="text-muted-foreground">{{ region }}</span>
                        </div>
                    </div>
                    <p class="mt-1 border-t pt-2 text-xs text-muted-foreground">
                        Email {{ user.email_verified_at ? `verified ${formatDate(user.email_verified_at)}` : 'not verified yet' }} · profile updated
                        {{ formatDate(user.updated_at) }}
                    </p>
                </section>

                <!-- Store -->
                <section v-if="user.seller" class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Store</h2>
                    <Link :href="sellerShow(user.seller.id)" class="font-medium hover:underline">{{ user.seller.store_name }}</Link>
                    <p class="text-sm text-muted-foreground">/sellers/{{ user.seller.slug }} · {{ user.seller.products_count ?? 0 }} {{ (user.seller.products_count ?? 0) === 1 ? "product" : "products" }}</p>
                    <p class="text-xs text-muted-foreground">
                        Seller access is {{ user.is_active_as_seller ? 'on' : 'off' }} for this account.
                    </p>
                </section>
                <section v-else class="rounded-xl border bg-card p-4 text-sm text-muted-foreground shadow-sm">
                    This customer does not own a store.
                </section>
            </div>
        </div>
    </div>
</template>
