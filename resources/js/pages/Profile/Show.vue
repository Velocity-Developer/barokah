<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Form, Head, Link, useForm, usePage } from '@inertiajs/vue3';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import ProfileController from '@/actions/App/Http/Controllers/Web/ProfileController';
import { UserRound, KeyRound, Home, LogOut } from '@lucide/vue';
import { send } from '@/routes/verification';
import { home } from '@/routes';
import { store as loginStore } from '@/routes/login';
import { show as profileShow } from '@/routes/profile';

const page = usePage();
const authUser = computed(() => page.props.auth.user as Record<string, unknown> | null);

const activeTab = ref<'profile' | 'password'>('profile');
const toast = computed(() => (page.props.toast as { type: string; message: string } | null) ?? null);

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

const props = defineProps<Props>();

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
                                class="h-20"
                                style="background-color: var(--brand-primary)"
                                aria-hidden="true"
                            ></div>
                            <div class="px-5 pb-5">
                                <div
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
                                    v-if="authUser?.is_active_as_seller"
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
                                    activeTab === 'password'
                                        ? 'bg-gray-50 font-medium text-gray-900'
                                        : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                                "
                                @click="activeTab = 'password'"
                            >
                                <KeyRound class="h-4 w-4 shrink-0" />
                                <span>Password</span>
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
                                    {{ activeTab === 'profile' ? 'Account Info' : 'Change Password' }}
                                </h1>
                                <p class="mt-0.5 text-sm text-gray-500">
                                    {{
                                        activeTab === 'profile'
                                            ? 'Update your profile and delivery information.'
                                            : 'Keep your account secure with a strong password.'
                                    }}
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
                    </section>
                </div>
            </div>
        </div>
    </MarketplaceLayout>
</template>
