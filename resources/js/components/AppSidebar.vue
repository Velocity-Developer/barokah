<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    CreditCard,
    FolderGit2,
    House,
    LayoutGrid,
    Package,
    Settings,
    ShoppingBag,
    Store,
    Tags,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminPaymentsIndex } from '@/routes/admin/payments';
import { index as adminProductsIndex } from '@/routes/admin/products';
import { index as sellerFlashSalesIndex } from '@/routes/seller/flash-sales';
import { MessageCircle, Ticket, UserCheck } from '@lucide/vue';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { index as adminSellerApprovalsIndex } from '@/routes/admin/seller-approvals';
import { show as adminSettingsShow } from '@/routes/admin/settings';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { index as adminFlashSalesIndex } from '@/routes/admin/flash-sales';
import { index as productsIndex } from '@/routes/products';
import {
    dashboard as sellerDashboard,
    settings as sellerSettings,
} from '@/routes/seller';
import { index as sellerProductsIndex } from '@/routes/seller/products';
import { index as sellerOrdersIndex } from '@/routes/seller/orders';
import type { NavItem } from '@/types';

type SidebarUser = {
    is_admin?: boolean;
    is_active_as_seller?: boolean;
};

const page = usePage();
const authUser = computed(
    () =>
        (page.props as unknown as { auth?: { user?: SidebarUser } }).auth
            ?.user,
);
const isAdmin = computed(() => authUser.value?.is_admin === true);
// Uses the same gate as the seller routes, so a pending application does not
// show seller links that would return 403.
const isSeller = computed(
    () =>
        (page.props as unknown as { auth?: { can?: { seller?: boolean } } }).auth
            ?.can?.seller === true,
);

const mainNavItems: NavItem[] = [
    {
        title: 'Marketplace',
        href: home(),
        icon: House,
    },
    {
        title: 'Products',
        href: productsIndex(),
        icon: ShoppingBag,
    },
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const sellerNavItems: NavItem[] = [
    {
        title: 'Seller dashboard',
        href: sellerDashboard(),
        icon: Store,
    },
    {
        title: 'Seller products',
        href: sellerProductsIndex(),
        icon: Package,
    },
    {
        title: 'Flash sales',
        href: sellerFlashSalesIndex(),
        icon: Tags,
    },
    { title: 'Coupons', href: '/seller/coupons', icon: Ticket },
    { title: 'Messages', href: '/profile?tab=messages', icon: MessageCircle },
    {
        title: 'Customer orders',
        href: sellerOrdersIndex(),
        icon: ShoppingBag,
    },
    {
        title: 'Store settings',
        href: sellerSettings(),
        icon: Settings,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Admin dashboard',
        href: adminDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Customers',
        href: adminUsersIndex(),
        icon: Users,
    },
    {
        title: 'Stores',
        href: adminSellersIndex(),
        icon: Store,
    },
    {
        title: 'Seller approvals',
        href: adminSellerApprovalsIndex(),
        icon: UserCheck,
    },
    {
        title: 'Products',
        href: adminProductsIndex(),
        icon: Package,
    },
    {
        title: 'Flash sales',
        href: adminFlashSalesIndex(),
        icon: Tags,
    },
    { title: 'Coupons', href: '/admin/coupons', icon: Ticket },
    {
        title: 'Categories',
        href: adminCategoriesIndex(),
        icon: Tags,
    },
    {
        title: 'Customer orders',
        href: adminOrdersIndex(),
        icon: ShoppingBag,
    },
    {
        title: 'PayNet transactions',
        href: adminPaymentsIndex(),
        icon: CreditCard,
    },
    {
        title: 'Settings',
        href: adminSettingsShow(),
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Platform" />
            <NavMain
                v-if="isSeller"
                :items="sellerNavItems"
                label="Seller"
            />
            <NavMain v-if="isAdmin" :items="adminNavItems" label="Admin" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
