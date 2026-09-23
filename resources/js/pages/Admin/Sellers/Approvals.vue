<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Check, Clock, Mail, MapPin, MessageCircle, Phone, Store, UserRound, Wallet, X } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { htmlToParagraphs } from '@/lib/richText';
import { index as sellerApprovalsIndex } from '@/routes/admin/seller-approvals';
import { approve, index as sellersIndex, reject, show } from '@/routes/admin/sellers';

type SellerApplication = {
    id: number;
    store_name: string;
    slug: string;
    description: string | null;
    profile_photo_url: string | null;
    banner_url: string | null;
    phone: string | null;
    whatsapp: string | null;
    location: string | null;
    bank_account: string | null;
    submitted_at: string | null;
    owner: {
        id: number | null;
        name: string | null;
        email: string | null;
        phone: string | null;
        joined_at: string | null;
    };
};

const props = defineProps<{
    applications: SellerApplication[];
    active_stores_count: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Seller approvals',
                href: sellerApprovalsIndex(),
            },
        ],
    },
});

const processingId = ref<number | null>(null);

// Description is rich-text HTML, so it is parsed once per application here.
const cards = computed(() =>
    props.applications.map((application) => ({
        ...application,
        paragraphs: htmlToParagraphs(application.description),
        days: daysWaiting(application.submitted_at),
    })),
);

const waitingLongest = computed(() => cards.value.reduce((longest, card) => Math.max(longest, card.days), 0));

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('en-GB', { dateStyle: 'medium' }) : '-';
}

function formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

function daysWaiting(value: string | null): number {
    if (!value) {
        return 0;
    }

    return Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 86_400_000));
}

function waitingLabel(value: string | null): string {
    const days = daysWaiting(value);

    if (days === 0) {
        return 'Submitted today';
    }

    return days === 1 ? 'Waiting 1 day' : `Waiting ${days} days`;
}

function whatsappUrl(number: string | null): string | null {
    const digits = (number ?? '').replace(/\D/g, '');

    return digits ? `https://wa.me/${digits}` : null;
}

function decide(application: SellerApplication, decision: 'approve' | 'reject'): void {
    const question =
        decision === 'approve'
            ? `Approve "${application.store_name}"? The store page goes live and the owner gets the seller dashboard.`
            : `Reject the application for "${application.store_name}"? The application is deleted and the user can apply again.`;

    if (!window.confirm(question)) {
        return;
    }

    const route = decision === 'approve' ? approve(application.id) : reject(application.id);

    router.post(
        route.url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                processingId.value = application.id;
            },
            onFinish: () => {
                processingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Seller approvals" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading
                variant="small"
                title="Seller approvals"
                description="Store applications from buyers. A store can sell only after it is approved."
            />
            <Link
                :href="sellersIndex()"
                class="inline-flex h-9 w-fit items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted"
            >
                <Store class="size-4" aria-hidden="true" /> All stores
                <span class="text-muted-foreground">({{ active_stores_count }} active)</span>
            </Link>
        </div>

        <div v-if="applications.length" class="flex flex-wrap items-center gap-2 text-sm">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-800 ring-1 ring-amber-200 ring-inset dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-900">
                <Clock class="size-4" aria-hidden="true" />
                {{ applications.length }} waiting for review
            </span>
            <span v-if="waitingLongest >= 3" class="text-muted-foreground">
                The oldest has been waiting {{ waitingLongest }} days.
            </span>
            <span v-else class="text-muted-foreground">Oldest applications are listed first.</span>
        </div>

        <section v-if="applications.length === 0" class="grid place-items-center gap-2 rounded-xl border bg-card p-10 text-center shadow-sm">
            <span class="grid size-12 place-items-center rounded-full bg-muted" aria-hidden="true">
                <Check class="size-6 text-muted-foreground" />
            </span>
            <p class="font-medium">No applications waiting</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                New store applications from buyers appear here for review.
            </p>
            <Link :href="sellersIndex()" class="mt-2 inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">
                Go to stores
            </Link>
        </section>

        <section v-for="application in cards" v-else :key="application.id" class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <!-- Most applications have no banner yet, so the strip only shows when there is one. -->
            <div
                v-if="application.banner_url"
                class="h-20 bg-cover bg-center"
                :style="{ backgroundImage: `url('${application.banner_url}')` }"
                aria-hidden="true"
            />

            <div class="grid gap-4 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div class="flex items-end gap-3">
                        <img
                            v-if="application.profile_photo_url"
                            :src="application.profile_photo_url"
                            alt=""
                            class="size-14 rounded-full border object-cover"
                            :class="application.banner_url ? '-mt-10 size-16 border-4 border-card' : ''"
                        />
                        <span
                            v-else
                            class="flex size-14 items-center justify-center rounded-full bg-[var(--accent-navy,#113366)] text-xl font-semibold text-white"
                            :class="application.banner_url ? '-mt-10 size-16 border-4 border-card' : ''"
                            aria-hidden="true"
                        >
                            {{ application.store_name.charAt(0).toUpperCase() }}
                        </span>
                        <div class="pb-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link :href="show(application.id)" class="text-lg font-semibold hover:underline">{{ application.store_name }}</Link>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="application.days >= 3
                                        ? 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900'
                                        : 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-900'"
                                >
                                    {{ waitingLabel(application.submitted_at) }}
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                /sellers/{{ application.slug }} · applied {{ formatDateTime(application.submitted_at) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border border-red-200 px-3 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                            :disabled="processingId === application.id"
                            @click="decide(application, 'reject')"
                        >
                            <X class="size-4" aria-hidden="true" /> Reject
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                            :disabled="processingId === application.id"
                            @click="decide(application, 'approve')"
                        >
                            <Check class="size-4" aria-hidden="true" /> Approve
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_280px]">
                    <div class="grid content-start gap-2">
                        <h2 class="text-sm font-medium">What they sell</h2>
                        <template v-if="application.paragraphs.length">
                            <p v-for="(paragraph, i) in application.paragraphs" :key="i" class="text-sm text-muted-foreground">
                                {{ paragraph }}
                            </p>
                        </template>
                        <p v-else class="text-sm text-muted-foreground italic">No description given.</p>

                        <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                            <div v-if="application.location" class="flex items-start gap-2">
                                <MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div>
                                    <dt class="sr-only">Store location</dt>
                                    <dd>{{ application.location }}</dd>
                                </div>
                            </div>
                            <div v-if="application.phone" class="flex items-start gap-2">
                                <Phone class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div>
                                    <dt class="sr-only">Store phone</dt>
                                    <dd>{{ application.phone }}</dd>
                                </div>
                            </div>
                            <div v-if="application.whatsapp" class="flex items-start gap-2">
                                <MessageCircle class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div>
                                    <dt class="sr-only">WhatsApp</dt>
                                    <dd>
                                        <a v-if="whatsappUrl(application.whatsapp)" :href="whatsappUrl(application.whatsapp)!" target="_blank" rel="noopener" class="hover:underline">
                                            {{ application.whatsapp }}
                                        </a>
                                        <template v-else>{{ application.whatsapp }}</template>
                                    </dd>
                                </div>
                            </div>
                            <div v-if="application.bank_account" class="flex items-start gap-2">
                                <Wallet class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                <div>
                                    <dt class="sr-only">Payout account</dt>
                                    <dd>{{ application.bank_account }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>

                    <div class="grid content-start gap-2 rounded-lg border bg-muted/40 p-3">
                        <h2 class="text-sm font-medium">Owner</h2>
                        <p class="flex items-center gap-2 text-sm">
                            <UserRound class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span>{{ application.owner.name ?? '-' }}</span>
                        </p>
                        <p v-if="application.owner.email" class="flex items-center gap-2 text-sm">
                            <Mail class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <a :href="`mailto:${application.owner.email}`" class="truncate hover:underline">{{ application.owner.email }}</a>
                        </p>
                        <p v-if="application.owner.phone" class="flex items-center gap-2 text-sm">
                            <Phone class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span>{{ application.owner.phone }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">Customer since {{ formatDate(application.owner.joined_at) }}</p>
                        <Link
                            v-if="application.owner.id"
                            :href="`/admin/users/${application.owner.id}`"
                            class="mt-1 inline-flex h-8 w-fit items-center rounded-md border bg-card px-3 text-sm font-medium hover:bg-muted"
                        >
                            View account
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
