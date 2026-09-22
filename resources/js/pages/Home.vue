<script setup lang="ts">
import BestSellerSection from '@/components/marketplace/BestSellerSection.vue';
import CategorySection from '@/components/marketplace/CategorySection.vue';
import FlashSaleSection from '@/components/marketplace/FlashSaleSection.vue';
import SellerSection from '@/components/marketplace/SellerSection.vue';
import QuickServices from '@/components/marketplace/QuickServices.vue';
import RecommendationSection from '@/components/marketplace/RecommendationSection.vue';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import type {
    HomeCategoryItem,
    HomeProductItem,
    HomeSellerItem,
} from '@/types/marketplace';

const props = defineProps<{
    categories: HomeCategoryItem[] | { data: HomeCategoryItem[] };
    latestProducts: HomeProductItem[] | { data: HomeProductItem[] };
    latestProductsHasMore?: boolean;
    flashSaleProducts: HomeProductItem[] | { data: HomeProductItem[] };
    bestSellerProducts: HomeProductItem[] | { data: HomeProductItem[] };
    sellers: HomeSellerItem[] | { data: HomeSellerItem[] };
}>();

function unwrap<T>(value: T[] | { data: T[] }): T[] {
    return Array.isArray(value) ? value : (value?.data ?? []);
}
</script>

<template>
    <MarketplaceLayout show-hero>
        <QuickServices :categories="unwrap(categories)" />
        <FlashSaleSection :products="unwrap(flashSaleProducts)" />
        <BestSellerSection :products="unwrap(bestSellerProducts)" />
        <SellerSection :sellers="unwrap(sellers)" />
        <CategorySection :categories="unwrap(categories)" />
        <RecommendationSection
            :products="unwrap(latestProducts)"
            :has-more="latestProductsHasMore"
        />
    </MarketplaceLayout>
</template>
