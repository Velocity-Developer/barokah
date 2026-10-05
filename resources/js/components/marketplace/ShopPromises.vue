<script setup lang="ts">
import { BadgeCheck, MessageCircleHeart, ShieldCheck, Truck } from '@lucide/vue';

// Short reassurances under the hero; each matches something the shop really does.
const promises = [
    { icon: BadgeCheck, title: 'Trusted sellers', text: 'Local stores, approved by us' },
    { icon: ShieldCheck, title: 'Secure payment', text: 'FPX and DuitNow' },
    { icon: Truck, title: 'Delivery', text: 'Across Malaysia' },
    { icon: MessageCircleHeart, title: 'Chat with sellers', text: 'Ask before you buy' },
];
</script>

<template>
    <section
        aria-label="Why shop with us"
        class="mt-4 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-4"
    >
        <div
            v-for="item in promises"
            :key="item.title"
            class="flex items-center gap-2.5 rounded-lg bg-white p-3 ring-1 ring-[var(--border-soft)] shadow-[var(--shadow-card)]"
        >
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--brand-primary-soft)] text-[var(--brand-primary)]"
                aria-hidden="true"
            >
                <component :is="item.icon" class="h-5 w-5" />
            </span>
            <span class="min-w-0">
                <span class="block text-xs font-bold text-[var(--accent-navy)] sm:text-sm">
                    {{ item.title }}
                </span>
                <span class="block text-[11px] text-[var(--text-muted)] sm:text-xs">
                    {{ item.text }}
                </span>
            </span>
        </div>
    </section>
</template>
