<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Search, ShoppingCart, UserRound, ChevronDown, LogOut, Settings2, Home as HomeIcon } from '@lucide/vue';
import { useCartStore } from '@/stores/cart';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { store as loginStore } from '@/routes/login';
import { show as profileShow } from '@/routes/profile';
import { home } from '@/routes';

const props = withDefaults(
    defineProps<{
        initialSearch?: string;
    }>(),
    { initialSearch: '' },
);

const emit = defineEmits<{
    search: [value: string];
}>();

const { getSettingValue } = useSettingsStore();
const { count: cartCount } = useCartStore();
const query = ref(props.initialSearch);
const keywords = computed(() => ['Keripik', 'Hijab', 'Kerudung']);
const page = usePage();
const authUser = computed(() => (page.props.auth?.user as Record<string, unknown> | null) ?? null);
const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLDivElement | null>(null);

function handleClickOutside(event: Event): void {
    if (userMenuRef.value && userMenuRef.value.contains(event.target as Node)) {
        return;
    }
    userMenuOpen.value = false;
}

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

function userInitials(): string {
    const name = String(authUser.value?.name ?? '');
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('') || 'U';
}

function siteName(): string {
    return getSettingValue<string>('branding.site_name', 'Barokah');
}

function logoUrl(): string {
    return getSettingValue<string>('branding.logo_url', '');
}

function submitSearch(): void {
    emit('search', query.value);
    router.get(
        '/products',
        { search: query.value || undefined },
        { preserveState: false, replace: false },
    );
}

function searchKeyword(keyword: string): void {
    query.value = keyword;
    submitSearch();
}
</script>

<template>
    <div
        class="sticky top-0 z-40 text-white shadow"
        style="background-color: var(--brand-primary)"
    >
        <div
            class="mx-auto flex w-full items-center gap-3 px-4 py-3 md:gap-6"
            style="max-width: var(--container-max); min-height: 76px"
        >
            <Link
                href="/"
                class="flex shrink-0 items-center gap-2"
                aria-label="Marketplace home"
            >
                <img
                    v-if="logoUrl()"
                    :src="logoUrl()"
                    :alt="siteName()"
                    class="h-9 max-w-32 rounded-sm bg-white object-contain p-1"
                />
                <span
                    v-else
                    class="flex h-9 w-9 items-center justify-center rounded-sm bg-white text-lg font-bold"
                    style="color: var(--brand-primary)"
                    aria-hidden="true"
                >
                    {{ siteName().charAt(0) }}
                </span>
                <span class="hidden text-xl font-bold tracking-tight sm:block">
                    {{ siteName() }}
                </span>
            </Link>

            <div class="min-w-0 flex-1">
                <form
                    class="flex items-center rounded-sm bg-white p-[3px]"
                    role="search"
                    @submit.prevent="submitSearch"
                >
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Search products, shops and more"
                        aria-label="Search products"
                        class="h-10 min-w-0 flex-1 rounded-sm bg-transparent px-3 text-sm text-[var(--text-primary)] outline-none"
                    />
                    <button
                        type="submit"
                        class="flex h-10 w-[60px] shrink-0 items-center justify-center rounded-sm text-white"
                        style="background-color: var(--brand-primary)"
                        aria-label="Search"
                    >
                        <Search class="h-5 w-5" />
                    </button>
                </form>
                <div
                    class="mt-1 hidden gap-3 overflow-hidden text-[11px] whitespace-nowrap text-white/90 md:flex"
                >
                    <button
                        v-for="keyword in keywords"
                        :key="keyword"
                        type="button"
                        class="cursor-pointer hover:underline"
                        @click="searchKeyword(keyword)"
                    >
                        {{ keyword }}
                    </button>
                </div>
            </div>

            <Link
                href="/cart"
                class="relative flex h-11 w-12 shrink-0 items-center justify-center rounded-sm transition hover:bg-white/10"
                aria-label="Shopping cart"
            >
                <ShoppingCart class="h-6 w-6" />
                <span
                    class="absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-white px-1 text-[10px] font-bold"
                    style="color: var(--brand-primary)"
                >
                    {{ cartCount }}
                </span>
            </Link>

            <div ref="userMenuRef" class="relative shrink-0">
                <template v-if="authUser">
                    <button
                        type="button"
                        class="flex h-11 items-center gap-1.5 rounded-sm px-2.5 transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30"
                        aria-haspopup="menu"
                        :aria-expanded="userMenuOpen"
                        @click="userMenuOpen = !userMenuOpen"
                    >
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-[13px] font-bold"
                            style="color: var(--brand-primary)"
                            aria-hidden="true"
                        >
                            {{ userInitials() }}
                        </span>
                        <span
                            class="hidden max-w-[110px] truncate text-sm font-medium text-white sm:block"
                        >
                            {{ authUser.name }}
                        </span>
                        <ChevronDown
                            class="hidden h-4 w-4 text-white/80 sm:block"
                            :class="{ 'rotate-180': userMenuOpen }"
                            aria-hidden="true"
                        />
                    </button>

                    <div
                        v-if="userMenuOpen"
                        role="menu"
                        class="absolute right-0 z-50 mt-2 w-56 origin-top-right overflow-hidden rounded-lg border border-gray-100 bg-white p-1 text-sm shadow-lg ring-1 ring-black/5"
                    >
                        <div class="border-b border-gray-100 px-3 py-2.5">
                            <p class="truncate font-semibold text-gray-900">
                                {{ authUser.name }}
                            </p>
                            <p class="truncate text-xs text-gray-500">
                                {{ authUser.email }}
                            </p>
                        </div>
                        <Link
                            :href="profileShow()"
                            role="menuitem"
                            class="mt-1 flex items-center gap-2.5 rounded-md px-3 py-2 text-gray-700 transition hover:bg-gray-50 hover:text-gray-900"
                            @click="userMenuOpen = false"
                        >
                            <UserRound class="h-4 w-4 shrink-0 text-gray-400" />
                            <span>My Profile</span>
                        </Link>
                        <Link
                            :href="home()"
                            role="menuitem"
                            class="flex items-center gap-2.5 rounded-md px-3 py-2 text-gray-700 transition hover:bg-gray-50 hover:text-gray-900"
                            @click="userMenuOpen = false"
                        >
                            <HomeIcon class="h-4 w-4 shrink-0 text-gray-400" />
                            <span>Marketplace</span>
                        </Link>
                        <div
                            class="my-1 h-px bg-gray-100"
                            role="separator"
                            aria-hidden="true"
                        ></div>
                        <Link
                            :href="loginStore()"
                            method="post"
                            as="button"
                            role="menuitem"
                            class="flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-red-600 transition hover:bg-red-50"
                            @click="userMenuOpen = false"
                        >
                            <LogOut class="h-4 w-4 shrink-0" />
                            <span>Log out</span>
                        </Link>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
