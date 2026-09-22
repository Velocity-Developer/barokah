<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CreditCard,
    FolderTree,
    LayoutGrid,
    MessageCircle,
    Package,
    Settings,
    ShoppingBag,
    Store,
    Tags,
    Ticket,
    UserCheck,
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
import { home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminCouponsIndex } from '@/routes/admin/coupons';
import { index as adminFlashSalesIndex } from '@/routes/admin/flash-sales';
import { index as adminOrdersIndex } from '@/routes/admin/orders';
import { index as adminPaymentsIndex } from '@/routes/admin/payments';
import { index as adminProductsIndex } from '@/routes/admin/products';
import { index as adminSellerApprovalsIndex } from '@/routes/admin/seller-approvals';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { show as adminSettingsShow } from '@/routes/admin/settings';
import { index as adminUsersIndex } from '@/routes/admin/users';
import { show as profileShow } from '@/routes/profile';
import {
    dashboard as sellerDashboard,
    settings as sellerSettings,
} from '@/routes/seller';
import { index as sellerCouponsIndex } from '@/routes/seller/coupons';
import { index as sellerFlashSalesIndex } from '@/routes/seller/flash-sales';
import { index as sellerOrdersIndex } from '@/routes/seller/orders';
import { index as sellerProductsIndex } from '@/routes/seller/products';
import type { NavItem } from '@/types';

type NavGroup = { label: string; items: NavItem[] };

const page = usePage();

// Same gates as the admin/seller route groups, so every link here opens.
const isAdmin = computed(() => page.props.auth?.can?.admin === true);
const isSeller = computed(() => page.props.auth?.can?.seller === true);
const pendingApprovals = computed(() => Number(page.props.auth?.pending_seller_approvals ?? 0));
const unreadMessages = computed(() => Number(page.props.auth?.unread_messages ?? 0));

const homeHref = computed(() => (isAdmin.value ? adminDashboard() : isSeller.value ? sellerDashboard() : home()));

const adminGroups = computed<NavGroup[]>(() => [
    {
        label: 'Overview',
        items: [{ title: 'Dashboard', href: adminDashboard(), icon: LayoutGrid }],
    },
    {
        label: 'Sales',
        items: [
            { title: 'Orders', href: adminOrdersIndex(), icon: ShoppingBag },
            { title: 'Payments', href: adminPaymentsIndex(), icon: CreditCard },
        ],
    },
    {
        label: 'Catalog',
        items: [
            { title: 'Products', href: adminProductsIndex(), icon: Package },
            { title: 'Categories', href: adminCategoriesIndex(), icon: FolderTree },
            { title: 'Flash sales', href: adminFlashSalesIndex(), icon: Tags },
            { title: 'Coupons', href: adminCouponsIndex(), icon: Ticket },
        ],
    },
    {
        label: 'Stores & customers',
        items: [
            { title: 'Stores', href: adminSellersIndex(), icon: Store },
            { title: 'Seller approvals', href: adminSellerApprovalsIndex(), icon: UserCheck, badge: pendingApprovals.value },
            { title: 'Customers', href: adminUsersIndex(), icon: Users },
        ],
    },
    {
        label: 'System',
        items: [{ title: 'Settings', href: adminSettingsShow(), icon: Settings }],
    },
]);

const sellerGroup = computed<NavGroup>(() => ({
    label: isAdmin.value ? 'My store' : 'Store',
    items: [
        { title: isAdmin.value ? 'Store dashboard' : 'Dashboard', href: sellerDashboard(), icon: LayoutGrid },
        { title: 'Orders', href: sellerOrdersIndex(), icon: ShoppingBag },
        { title: 'Products', href: sellerProductsIndex(), icon: Package },
        { title: 'Flash sales', href: sellerFlashSalesIndex(), icon: Tags },
        { title: 'Coupons', href: sellerCouponsIndex(), icon: Ticket },
        { title: 'Messages', href: profileShow({ query: { tab: 'messages' } }), icon: MessageCircle, badge: unreadMessages.value },
        { title: 'Store settings', href: sellerSettings(), icon: Settings },
    ],
}));

const footerNavItems: NavItem[] = [{ title: 'View storefront', href: home(), icon: Store }];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <template v-if="isAdmin">
                <NavMain v-for="group in adminGroups" :key="group.label" :items="group.items" :label="group.label" />
            </template>
            <NavMain v-if="isSeller" :items="sellerGroup.items" :label="sellerGroup.label" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
