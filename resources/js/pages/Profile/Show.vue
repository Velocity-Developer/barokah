<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Form, Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import ProductCard from '@/components/product/ProductCard.vue';
import ProfileController from '@/actions/App/Http/Controllers/Web/ProfileController';
import { UserRound, KeyRound, Home, LogOut, Store, Heart, ImageIcon, BadgeCheck } from '@lucide/vue';
import { send } from '@/routes/verification';
import { home } from '@/routes';
import { store as loginStore } from '@/routes/login';
import { show as profileShow } from '@/routes/profile';
import { dashboard as sellerDashboard } from '@/routes/seller';
import { show as sellerShow } from '@/routes/sellers';
import {
    toProductCardData,
    type HomeProductItem,
    type HomeSellerItem,
} from '@/types/marketplace';

const page = usePage();
const authUser = computed(() => page.props.auth.user as Record<string, unknown> | null);

type ProfileTab = 'profile' | 'media' | 'password' | 'seller' | 'following_sellers' | 'favorite_products';

const profileTabs: ProfileTab[] = ['profile', 'media', 'password', 'seller', 'following_sellers', 'favorite_products'];

function tabFromUrl(): ProfileTab {
    const requested = new URLSearchParams(page.url.split('?')[1] ?? '').get('tab');

    return profileTabs.includes(requested as ProfileTab) ? (requested as ProfileTab) : 'profile';
}

const activeTab = ref<ProfileTab>(tabFromUrl());

watch(
    () => page.url,
    () => {
        activeTab.value = tabFromUrl();
    },
);

const tabHeadings: Record<ProfileTab, { title: string; subtitle: string }> = {
    profile: { title: 'Account Info', subtitle: 'Update your profile and delivery information.' },
    media: { title: 'Foto Profil & Banner', subtitle: 'Atur foto profil dan gambar latar kartu profil Anda.' },
    seller: { title: 'Seller', subtitle: 'Buka toko Anda sendiri di marketplace.' },
    password: { title: 'Change Password', subtitle: 'Keep your account secure with a strong password.' },
    following_sellers: { title: 'Toko Diikuti', subtitle: 'Toko yang Anda ikuti.' },
    favorite_products: { title: 'Produk Favorit', subtitle: 'Produk yang Anda simpan sebagai favorit.' },
};
const toast = computed(() => (page.props.toast as { type: string; message: string } | null) ?? null);

type SellerApplication = {
    store_name: string;
    slug: string;
    status: 'pending' | 'active' | 'suspended';
    submitted_at: string | null;
};

type Props = {
    sellerApplication?: SellerApplication | null;
    mustVerifyEmail: boolean;
    status?: string;
    followedSellers?: HomeSellerItem[] | { data: HomeSellerItem[] };
    favoriteProducts?: HomeProductItem[] | { data: HomeProductItem[] };
};

const props = defineProps<Props>();

function unwrapList<T>(value: T[] | { data: T[] } | undefined): T[] {
    if (!value) {
        return [];
    }

    return Array.isArray(value) ? value : (value.data ?? []);
}

const followedSellerList = computed<HomeSellerItem[]>(() => unwrapList(props.followedSellers));

const favoriteProductCards = computed(() =>
    unwrapList(props.favoriteProducts).map((product) => toProductCardData(product)),
);

const initials = computed(() => {
    const name = String(authUser.value?.name ?? 'U');
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('') || 'U';
});

type ProfileFormData = {
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    post_code: string;
};

function snapshotFromUser(u: Record<string, unknown> | null): ProfileFormData {
    return {
        name: String(u?.name ?? ''),
        email: String(u?.email ?? ''),
        phone: String((u?.phone as string | undefined) ?? ''),
        address: String((u?.address as string | undefined) ?? ''),
        city: String((u?.city as string | undefined) ?? ''),
        state: String((u?.state as string | undefined) ?? ''),
        post_code: String((u?.post_code as string | undefined) ?? ''),
    };
}

const profile = useForm<ProfileFormData>(snapshotFromUser(authUser.value));

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

watch(
    authUser,
    (newUser) => {
        if (profile.processing) {
            return;
        }
        const snapshot = snapshotFromUser(newUser);
        if (profile.name !== snapshot.name) profile.name = snapshot.name;
        if (profile.email !== snapshot.email) profile.email = snapshot.email;
        if (profile.phone !== snapshot.phone) profile.phone = snapshot.phone;
        if (profile.address !== snapshot.address) profile.address = snapshot.address;
        if (profile.city !== snapshot.city) profile.city = snapshot.city;
        if (profile.state !== snapshot.state) profile.state = snapshot.state;
        if (profile.post_code !== snapshot.post_code) profile.post_code = snapshot.post_code;
        profile.defaults();
    },
    { deep: true },
);

function saveProfile(): void {
    profile.submit(ProfileController.update(), {
        preserveScroll: true,
        onSuccess: () => {
            profile.defaults();
        },
    });
}

const media = useForm<{
    profile_photo: File | null;
    banner: File | null;
    remove_profile_photo: boolean;
    remove_banner: boolean;
}>({
    profile_photo: null,
    banner: null,
    remove_profile_photo: false,
    remove_banner: false,
});

const photoPreview = ref<string | null>(null);
const bannerPreview = ref<string | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const bannerInput = ref<HTMLInputElement | null>(null);

const currentPhotoUrl = computed(() => (authUser.value?.profile_photo_url as string | null | undefined) ?? null);
const currentBannerUrl = computed(() => (authUser.value?.banner_url as string | null | undefined) ?? null);

const shownPhotoUrl = computed(() =>
    photoPreview.value ?? (media.remove_profile_photo ? null : currentPhotoUrl.value),
);
const shownBannerUrl = computed(() =>
    bannerPreview.value ?? (media.remove_banner ? null : currentBannerUrl.value),
);

function replacePreview(target: typeof photoPreview, file: File | null): void {
    if (target.value) {
        URL.revokeObjectURL(target.value);
    }
    target.value = file ? URL.createObjectURL(file) : null;
}

function pickPhoto(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    media.profile_photo = file;
    media.remove_profile_photo = false;
    replacePreview(photoPreview, file);
}

function pickBanner(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    media.banner = file;
    media.remove_banner = false;
    replacePreview(bannerPreview, file);
}

function removePhoto(): void {
    media.profile_photo = null;
    media.remove_profile_photo = true;
    replacePreview(photoPreview, null);
    if (photoInput.value) photoInput.value.value = '';
}

function removeBanner(): void {
    media.banner = null;
    media.remove_banner = true;
    replacePreview(bannerPreview, null);
    if (bannerInput.value) bannerInput.value.value = '';
}

function resetMedia(): void {
    media.reset();
    media.clearErrors();
    replacePreview(photoPreview, null);
    replacePreview(bannerPreview, null);
    if (photoInput.value) photoInput.value.value = '';
    if (bannerInput.value) bannerInput.value.value = '';
}

const mediaDirty = computed(
    () => media.profile_photo !== null || media.banner !== null || media.remove_profile_photo || media.remove_banner,
);

function saveMedia(): void {
    media.submit(ProfileController.updateMedia(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => resetMedia(),
    });
}

onBeforeUnmount(() => {
    replacePreview(photoPreview, null);
    replacePreview(bannerPreview, null);
});

const sellerApplication = computed<SellerApplication | null>(() => props.sellerApplication ?? null);

const sellerForm = useForm({ store_name: '', description: '' });

function submitSellerApplication(): void {
    sellerForm.post(ProfileController.applyAsSeller().url, {
        preserveScroll: true,
        onSuccess: () => sellerForm.reset(),
    });
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
        : '-';
}

function savePassword(): void {
    password.submit(ProfileController.updatePassword(), {
        preserveScroll: true,
        onSuccess: () => {
            password.reset();
            password.defaults();
        },
    });
}
</script>

<template>
    <MarketplaceLayout>
        <Head title="My Profile" />

        <div class="min-h-screen bg-[var(--bg-light)]">
            <div class="mx-auto w-full px-4 py-6 md:py-8" style="max-width: var(--container-max)">
                <nav class="mb-6 flex items-center gap-2 text-sm text-gray-500">
                    <Link :href="home()" class="hover:text-gray-700">Home</Link>
                    <span aria-hidden="true">/</span>
                    <span class="text-gray-700">My Profile</span>
                </nav>

                <div
                    v-if="toast"
                    class="mb-5 rounded-md border px-4 py-3 text-sm"
                    :class="
                        toast.type === 'success'
                            ? 'border-green-200 bg-green-50 text-green-700'
                            : 'border-red-200 bg-red-50 text-red-700'
                    "
                >
                    {{ toast.message }}
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-[280px_1fr]">
                    <aside class="space-y-4">
                        <div
                            class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100"
                        >
                            <div
                                class="h-20 bg-cover bg-center"
                                :style="
                                    shownBannerUrl
                                        ? { backgroundImage: `url('${shownBannerUrl}')` }
                                        : { backgroundColor: 'var(--brand-primary)' }
                                "
                                aria-hidden="true"
                            ></div>
                            <div class="px-5 pb-5">
                                <img
                                    v-if="shownPhotoUrl"
                                    :src="shownPhotoUrl"
                                    alt=""
                                    class="-mt-8 h-16 w-16 rounded-full border-4 border-white bg-gray-100 object-cover shadow-sm"
                                />
                                <div
                                    v-else
                                    class="-mt-8 inline-flex h-16 w-16 items-center justify-center rounded-full border-4 border-white bg-gray-100 text-2xl font-bold text-gray-700 shadow-sm"
                                    aria-hidden="true"
                                >
                                    {{ initials }}
                                </div>
                                <h2 class="mt-3 truncate text-lg font-semibold text-gray-900">
                                    {{ authUser?.name ?? 'User' }}
                                </h2>
                                <p class="mt-0.5 truncate text-sm text-gray-500">
                                    {{ authUser?.email }}
                                </p>
                                <p
                                    v-if="sellerApplication?.status === 'active'"
                                    class="mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    style="
                                        background-color: color-mix(
                                            in srgb,
                                            var(--brand-primary) 12%,
                                            white
                                        );
                                        color: var(--brand-primary);
                                    "
                                >
                                    Seller
                                </p>
                            </div>
                        </div>

                        <nav
                            class="overflow-hidden rounded-lg bg-white p-1 text-sm shadow-sm ring-1 ring-gray-100"
                            aria-label="Profile sections"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left transition"
                                :class="
                                    activeTab === 'profile'
                                        ? 'bg-gray-50 font-medium text-gray-900'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                "
                                @click="activeTab = 'profile'"
                            >
                                <UserRound class="h-4 w-4 shrink-0" />
                                <span>Account Info</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left transition"
                                :class="
                                    activeTab === 'media'
                                        ? 'bg-gray-50 font-medium text-gray-900'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                "
                                @click="activeTab = 'media'"
                            >
                                <ImageIcon class="h-4 w-4 shrink-0" />
                                <span>Foto &amp; Banner</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left transition"
                                :class="
                                    activeTab === 'password'
                                        ? 'bg-gray-50 font-medium text-gray-900'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                "
                                @click="activeTab = 'password'"
                            >
                                <KeyRound class="h-4 w-4 shrink-0" />
                                <span>Password</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left transition"
                                :class="
                                    activeTab === 'seller'
                                        ? 'bg-gray-50 font-medium text-gray-900'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                "
                                @click="activeTab = 'seller'"
                            >
                                <BadgeCheck class="h-4 w-4 shrink-0" />
                                <span>Seller</span>
                                <span
                                    v-if="sellerApplication?.status === 'pending'"
                                    class="ml-auto rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                                >
                                    Menunggu
                                </span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left transition"
                                :class="
                                    activeTab === 'following_sellers'
                                        ? 'bg-gray-50 font-medium text-gray-900'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                "
                                @click="activeTab = 'following_sellers'"
                            >
                                <Store class="h-4 w-4 shrink-0" />
                                <span>Toko Diikuti</span>
                                <span class="ml-auto text-xs text-gray-400">{{ followedSellerList.length }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left transition"
                                :class="
                                    activeTab === 'favorite_products'
                                        ? 'bg-gray-50 font-medium text-gray-900'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                "
                                @click="activeTab = 'favorite_products'"
                            >
                                <Heart class="h-4 w-4 shrink-0" />
                                <span>Produk Favorit</span>
                                <span class="ml-auto text-xs text-gray-400">{{ favoriteProductCards.length }}</span>
                            </button>
                            <hr class="my-1 border-gray-100" />
                            <Link
                                :href="home()"
                                class="flex items-center gap-3 rounded-md px-3 py-2.5 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900"
                            >
                                <Home class="h-4 w-4 shrink-0" />
                                <span>Marketplace</span>
                            </Link>
                            <Link
                                :href="loginStore()"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-gray-600 transition hover:bg-gray-50 hover:text-red-600"
                            >
                                <LogOut class="h-4 w-4 shrink-0" />
                                <span>Log out</span>
                            </Link>
                        </nav>
                    </aside>

                    <section
                        class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100"
                    >
                        <header
                            class="flex items-center justify-between border-b border-gray-100 px-6 py-4"
                        >
                            <div>
                                <h1 class="text-lg font-semibold text-gray-900">
                                    {{ tabHeadings[activeTab].title }}
                                </h1>
                                <p class="mt-0.5 text-sm text-gray-500">
                                    {{ tabHeadings[activeTab].subtitle }}
                                </p>
                            </div>
                        </header>

                        <div v-show="activeTab === 'profile'" class="px-6 py-5">
                            <Form
                                @submit.prevent="saveProfile"
                                class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2"
                            >
                                <div class="grid gap-1.5">
                                    <label for="name" class="text-sm font-medium text-gray-700">
                                        Full Name
                                    </label>
                                    <input
                                        id="name"
                                        v-model="profile.name"
                                        type="text"
                                        required
                                        autocomplete="name"
                                        class="h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="e.g. Ahmad Fauzi"
                                    />
                                    <p v-if="profile.errors.name" class="text-xs text-red-600">
                                        {{ profile.errors.name }}
                                    </p>
                                </div>

                                <div class="grid gap-1.5">
                                    <label for="email" class="text-sm font-medium text-gray-700">
                                        Email Address
                                    </label>
                                    <input
                                        id="email"
                                        v-model="profile.email"
                                        type="email"
                                        required
                                        autocomplete="username"
                                        class="h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="you@example.com"
                                    />
                                    <p v-if="profile.errors.email" class="text-xs text-red-600">
                                        {{ profile.errors.email }}
                                    </p>
                                </div>

                                <div v-if="props.mustVerifyEmail && !authUser?.email_verified_at"
                                    class="md:col-span-2"
                                >
                                    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                        Your email address is unverified.
                                        <Link
                                            :href="send()"
                                            as="button"
                                            class="ml-1 font-semibold underline underline-offset-2 hover:text-amber-800"
                                        >
                                            Resend verification email
                                        </Link>
                                        <p
                                            v-if="props.status === 'verification-link-sent'"
                                            class="mt-1 text-xs text-green-700"
                                        >
                                            A new verification link has been sent to your email address.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <label for="phone" class="text-sm font-medium text-gray-700">
                                        Phone Number
                                    </label>
                                    <input
                                        id="phone"
                                        v-model="profile.phone"
                                        type="tel"
                                        autocomplete="tel"
                                        class="h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="+6012 345 6789"
                                    />
                                    <p v-if="profile.errors.phone" class="text-xs text-red-600">
                                        {{ profile.errors.phone }}
                                    </p>
                                </div>

                                <div class="grid gap-1.5">
                                    <label for="post_code" class="text-sm font-medium text-gray-700">
                                        Postcode
                                    </label>
                                    <input
                                        id="post_code"
                                        v-model="profile.post_code"
                                        type="text"
                                        autocomplete="postal-code"
                                        class="h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="50000"
                                    />
                                    <p v-if="profile.errors.post_code" class="text-xs text-red-600">
                                        {{ profile.errors.post_code }}
                                    </p>
                                </div>

                                <div class="grid gap-1.5 md:col-span-2">
                                    <label for="address" class="text-sm font-medium text-gray-700">
                                        Address
                                    </label>
                                    <textarea
                                        id="address"
                                        v-model="profile.address"
                                        rows="3"
                                        autocomplete="street-address"
                                        class="w-full resize-y rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="Street, building, or other delivery details"
                                    ></textarea>
                                    <p v-if="profile.errors.address" class="text-xs text-red-600">
                                        {{ profile.errors.address }}
                                    </p>
                                </div>

                                <div class="grid gap-1.5">
                                    <label for="city" class="text-sm font-medium text-gray-700">
                                        City
                                    </label>
                                    <input
                                        id="city"
                                        v-model="profile.city"
                                        type="text"
                                        autocomplete="address-level2"
                                        class="h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="Kuala Lumpur"
                                    />
                                    <p v-if="profile.errors.city" class="text-xs text-red-600">
                                        {{ profile.errors.city }}
                                    </p>
                                </div>

                                <div class="grid gap-1.5">
                                    <label for="state" class="text-sm font-medium text-gray-700">
                                        State / Region
                                    </label>
                                    <input
                                        id="state"
                                        v-model="profile.state"
                                        type="text"
                                        autocomplete="address-level1"
                                        class="h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="Wilayah Persekutuan"
                                    />
                                    <p v-if="profile.errors.state" class="text-xs text-red-600">
                                        {{ profile.errors.state }}
                                    </p>
                                </div>

                                <div
                                    class="flex items-center justify-end gap-3 pt-2 md:col-span-2 md:border-t md:border-gray-100 md:pt-4"
                                >
                                    <button
                                        type="submit"
                                        :disabled="profile.processing"
                                        class="inline-flex h-11 items-center justify-center rounded-md px-6 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                                        style="background-color: var(--brand-primary)"
                                    >
                                        {{ profile.processing ? 'Saving...' : 'Save Changes' }}
                                    </button>
                                </div>
                            </Form>
                        </div>

                        <div v-show="activeTab === 'media'" class="px-6 py-5">
                            <form class="grid gap-6" @submit.prevent="saveMedia">
                                <div class="grid gap-2">
                                    <p class="text-sm font-medium text-gray-700">Banner</p>
                                    <div
                                        class="flex h-32 w-full items-center justify-center overflow-hidden rounded-md bg-cover bg-center text-sm text-white/90 ring-1 ring-gray-100 md:h-40"
                                        :style="
                                            shownBannerUrl
                                                ? { backgroundImage: `url('${shownBannerUrl}')` }
                                                : { backgroundColor: 'var(--brand-primary)' }
                                        "
                                    >
                                        <span v-if="!shownBannerUrl">Belum ada banner</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label
                                            for="banner"
                                            class="inline-flex h-9 cursor-pointer items-center rounded-md border border-gray-200 px-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                        >
                                            Pilih gambar
                                        </label>
                                        <input
                                            id="banner"
                                            ref="bannerInput"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            class="sr-only"
                                            @change="pickBanner"
                                        />
                                        <button
                                            v-if="shownBannerUrl"
                                            type="button"
                                            class="h-9 rounded-md px-3 text-sm text-red-600 transition hover:bg-red-50"
                                            @click="removeBanner"
                                        >
                                            Hapus banner
                                        </button>
                                        <span class="text-xs text-gray-500">JPG, PNG, atau WEBP, maks. 4 MB. Disarankan rasio lebar ±4:1.</span>
                                    </div>
                                    <p v-if="media.errors.banner" class="text-xs text-red-600">
                                        {{ media.errors.banner }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <p class="text-sm font-medium text-gray-700">Foto profil</p>
                                    <div class="flex flex-wrap items-center gap-4">
                                        <img
                                            v-if="shownPhotoUrl"
                                            :src="shownPhotoUrl"
                                            alt="Pratinjau foto profil"
                                            class="h-20 w-20 rounded-full object-cover ring-1 ring-gray-100"
                                        />
                                        <div
                                            v-else
                                            class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 text-2xl font-bold text-gray-700"
                                            aria-hidden="true"
                                        >
                                            {{ initials }}
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <label
                                                for="profile_photo"
                                                class="inline-flex h-9 cursor-pointer items-center rounded-md border border-gray-200 px-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                            >
                                                Pilih foto
                                            </label>
                                            <input
                                                id="profile_photo"
                                                ref="photoInput"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                class="sr-only"
                                                @change="pickPhoto"
                                            />
                                            <button
                                                v-if="shownPhotoUrl"
                                                type="button"
                                                class="h-9 rounded-md px-3 text-sm text-red-600 transition hover:bg-red-50"
                                                @click="removePhoto"
                                            >
                                                Hapus foto
                                            </button>
                                            <span class="text-xs text-gray-500">JPG, PNG, atau WEBP, maks. 2 MB.</span>
                                        </div>
                                    </div>
                                    <p v-if="media.errors.profile_photo" class="text-xs text-red-600">
                                        {{ media.errors.profile_photo }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-3 border-t border-gray-100 pt-5">
                                    <button
                                        type="submit"
                                        :disabled="media.processing || !mediaDirty"
                                        class="inline-flex h-11 items-center justify-center rounded-md px-6 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                                        style="background-color: var(--brand-primary)"
                                    >
                                        {{ media.processing ? 'Menyimpan...' : 'Simpan' }}
                                    </button>
                                    <button
                                        v-if="mediaDirty && !media.processing"
                                        type="button"
                                        class="h-11 rounded-md px-4 text-sm text-gray-600 transition hover:bg-gray-50"
                                        @click="resetMedia"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div v-show="activeTab === 'seller'" class="px-6 py-5">
                            <div
                                v-if="sellerApplication?.status === 'pending'"
                                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800"
                            >
                                <p class="font-semibold">Menunggu persetujuan admin</p>
                                <p class="mt-1">
                                    Pengajuan toko <strong>{{ sellerApplication.store_name }}</strong>
                                    dikirim {{ formatDate(sellerApplication.submitted_at) }}. Anda akan bisa membuka
                                    Dashboard Seller setelah admin menyetujuinya.
                                </p>
                            </div>

                            <div
                                v-else-if="sellerApplication?.status === 'active'"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-green-200 bg-green-50 px-4 py-4 text-sm text-green-800"
                            >
                                <div>
                                    <p class="font-semibold">Toko aktif</p>
                                    <p class="mt-1">{{ sellerApplication.store_name }} sudah aktif sebagai seller.</p>
                                </div>
                                <Link
                                    :href="sellerDashboard()"
                                    class="inline-flex h-10 items-center rounded-md px-4 text-sm font-semibold text-white"
                                    style="background-color: var(--brand-primary)"
                                >
                                    Buka Dashboard Seller
                                </Link>
                            </div>

                            <div
                                v-else-if="sellerApplication?.status === 'suspended'"
                                class="rounded-md border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700"
                            >
                                <p class="font-semibold">Toko ditangguhkan</p>
                                <p class="mt-1">
                                    Toko {{ sellerApplication.store_name }} sedang ditangguhkan. Hubungi admin untuk
                                    informasi lebih lanjut.
                                </p>
                            </div>

                            <form v-else class="grid gap-5" @submit.prevent="submitSellerApplication">
                                <p class="text-sm text-gray-600">
                                    Ajukan akun Anda menjadi seller. Setelah admin menyetujui, Anda bisa mengelola
                                    produk, pesanan, dan pengaturan toko di Dashboard Seller.
                                </p>
                                <div class="grid gap-1.5">
                                    <label for="store_name" class="text-sm font-medium text-gray-700">
                                        Nama toko
                                    </label>
                                    <input
                                        id="store_name"
                                        v-model="sellerForm.store_name"
                                        type="text"
                                        required
                                        maxlength="255"
                                        class="h-11 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="mis. Keripik Barokah"
                                    />
                                    <p v-if="sellerForm.errors.store_name" class="text-xs text-red-600">
                                        {{ sellerForm.errors.store_name }}
                                    </p>
                                </div>
                                <div class="grid gap-1.5">
                                    <label for="store_description" class="text-sm font-medium text-gray-700">
                                        Deskripsi toko <span class="font-normal text-gray-400">(opsional)</span>
                                    </label>
                                    <textarea
                                        id="store_description"
                                        v-model="sellerForm.description"
                                        rows="4"
                                        maxlength="2000"
                                        class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20"
                                        placeholder="Produk apa yang akan Anda jual?"
                                    ></textarea>
                                    <p v-if="sellerForm.errors.description" class="text-xs text-red-600">
                                        {{ sellerForm.errors.description }}
                                    </p>
                                </div>
                                <div>
                                    <button
                                        type="submit"
                                        :disabled="sellerForm.processing"
                                        class="inline-flex h-11 items-center justify-center rounded-md px-6 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                                        style="background-color: var(--brand-primary)"
                                    >
                                        {{ sellerForm.processing ? 'Mengirim...' : 'Aktifkan Seller' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div v-show="activeTab === 'password'" class="px-6 py-5">
                            <Form
                                @submit.prevent="savePassword"
                                class="grid grid-cols-1 gap-y-5 md:max-w-2xl"
                            >
                                <div class="grid gap-1.5">
                                    <label
                                        for="current_password"
                                        class="text-sm font-medium text-gray-700"
                                    >
                                        Current Password
                                    </label>
                                    <PasswordInput
                                        id="current_password"
                                        v-model="password.current_password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Enter your current password"
                                    />
                                    <p v-if="password.errors.current_password" class="text-xs text-red-600">
                                        {{ password.errors.current_password }}
                                    </p>
                                </div>

                                <div class="grid gap-1.5">
                                    <label for="password" class="text-sm font-medium text-gray-700">
                                        New Password
                                    </label>
                                    <PasswordInput
                                        id="password"
                                        v-model="password.password"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Use at least 8 characters"
                                    />
                                    <p v-if="password.errors.password" class="text-xs text-red-600">
                                        {{ password.errors.password }}
                                    </p>
                                </div>

                                <div class="grid gap-1.5">
                                    <label
                                        for="password_confirmation"
                                        class="text-sm font-medium text-gray-700"
                                    >
                                        Confirm New Password
                                    </label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        v-model="password.password_confirmation"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Re-type the new password"
                                    />
                                    <p v-if="password.errors.password_confirmation" class="text-xs text-red-600">
                                        {{ password.errors.password_confirmation }}
                                    </p>
                                </div>

                                <div
                                    class="flex items-center justify-end gap-3 pt-2 md:border-t md:border-gray-100 md:pt-4"
                                >
                                    <button
                                        type="submit"
                                        :disabled="password.processing"
                                        class="inline-flex h-11 items-center justify-center rounded-md px-6 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                                        style="background-color: var(--brand-primary)"
                                    >
                                        {{ password.processing ? 'Updating...' : 'Update Password' }}
                                    </button>
                                </div>
                            </Form>
                        </div>

                        <div v-show="activeTab === 'following_sellers'" class="px-6 py-5">
                            <p
                                v-if="followedSellerList.length === 0"
                                class="py-10 text-center text-sm text-gray-500"
                            >
                                Belum mengikuti toko manapun.
                            </p>
                            <ul v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <li
                                    v-for="seller in followedSellerList"
                                    :key="seller.id"
                                    class="flex items-center gap-3 rounded-md border border-gray-100 p-3"
                                >
                                    <img
                                        v-if="seller.profile_photo_url"
                                        :src="seller.profile_photo_url"
                                        :alt="seller.store_name"
                                        class="size-12 shrink-0 rounded-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-[var(--accent-navy)] text-lg font-semibold text-white"
                                        aria-hidden="true"
                                    >
                                        {{ seller.store_name.charAt(0).toUpperCase() }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900">
                                            {{ seller.store_name }}
                                        </p>
                                        <p class="truncate text-xs text-gray-500">
                                            {{ seller.city || seller.state || 'Marketplace seller' }}
                                            · {{ seller.followers_count ?? 0 }} pengikut
                                        </p>
                                    </div>
                                    <Link
                                        :href="sellerShow(seller.slug)"
                                        class="shrink-0 rounded-md border px-3 py-1.5 text-xs font-medium transition hover:bg-gray-50"
                                        style="border-color: var(--brand-primary); color: var(--brand-primary)"
                                    >
                                        Lihat Toko
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <div v-show="activeTab === 'favorite_products'" class="px-6 py-5">
                            <p
                                v-if="favoriteProductCards.length === 0"
                                class="py-10 text-center text-sm text-gray-500"
                            >
                                Produk favorit masih kosong.
                            </p>
                            <div
                                v-else
                                class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4"
                            >
                                <ProductCard
                                    v-for="card in favoriteProductCards"
                                    :key="card.id"
                                    :product="card"
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </MarketplaceLayout>
</template>
