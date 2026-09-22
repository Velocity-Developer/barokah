<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, Clock, Mail, MapPin, MessageCircle, Phone } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import MarketplaceLayout from '@/layouts/MarketplaceLayout.vue';
import { sellerCenter } from '@/routes';
import { useSettingsStore } from '@/stores/settings';

type Faq = { question: string; answer: string; link?: { label: string; href: string } };
type FaqGroup = { title: string; items: Faq[] };
type ContactItem = { icon: Component; label: string; value: string; href?: string; external?: boolean };

const { getSettingValue, formatAmount } = useSettingsStore();

const siteName = computed(() => getSettingValue<string>('branding.site_name', 'Barokah'));

const paymentMethods = computed<string[]>(() => {
    const methods: string[] = [];

    if (getSettingValue<boolean>('payment.bank_transfer_enabled', false)) methods.push('bank transfer');
    if (getSettingValue<boolean>('payment.qr_code_enabled', false)) methods.push('QR code');
    if (getSettingValue<boolean>('payment.paynet_enabled', false)) {
        if (getSettingValue<boolean>('payment.fpx_enabled', false)) methods.push('FPX online banking');
        if (getSettingValue<boolean>('payment.duitnow_enabled', false)) methods.push('DuitNow');
    }

    return methods;
});

function listText(items: string[]): string {
    if (items.length <= 1) return items[0] ?? '';

    return `${items.slice(0, -1).join(', ')} and ${items.at(-1)}`;
}

const shippingAnswer = computed<string>(() => {
    const parts: string[] = [];

    if (getSettingValue<string>('shipping.method', 'fixed') === 'fixed') {
        const rate = Number(getSettingValue<string>('shipping.fixed_rate', '0'));
        parts.push(`Shipping is a flat ${formatAmount(rate)} per order.`);
    } else {
        parts.push('Shipping costs are calculated at checkout based on your delivery address.');
    }

    if (getSettingValue<boolean>('shipping.free_shipping_enabled', false)) {
        const threshold = Number(getSettingValue<string>('shipping.free_shipping_threshold', '0'));
        parts.push(`Orders of ${formatAmount(threshold)} or more ship for free.`);
    }

    parts.push('Each store ships its own items, so an order with products from several stores may arrive in separate parcels.');

    return parts.join(' ');
});

const faqGroups = computed<FaqGroup[]>(() => {
    const expiration = getSettingValue<number>('checkout.order_expiration_minutes', 30);
    const methods = paymentMethods.value;

    return [
        {
            title: 'Orders & payment',
            items: [
                {
                    question: 'How do I place an order?',
                    answer: 'Open a product, choose the quantity and tap Buy Now, or add several products to your cart and check out once. Fill in your delivery details, pick a payment method and confirm the order.',
                    link: { label: 'Browse products', href: '/products' },
                },
                {
                    question: 'Which payment methods can I use?',
                    answer: methods.length
                        ? `You can pay with ${listText(methods)}. The available options are shown on the checkout page.`
                        : 'The available payment options are shown on the checkout page.',
                },
                {
                    question: 'How long do I have to pay?',
                    answer: `Please complete your payment within ${expiration} minutes of placing the order. Unpaid orders expire after that and the items go back into stock, so you would need to place a new order.`,
                },
                {
                    question: 'How do I use a voucher?',
                    answer: 'Enter the voucher code on the checkout page. Some vouchers need a minimum spend or only apply to one store. See all active vouchers on the Vouchers page.',
                    link: { label: 'View vouchers', href: '/coupons' },
                },
            ],
        },
        {
            title: 'Shipping & tracking',
            items: [
                { question: 'How much is shipping?', answer: shippingAnswer.value },
                {
                    question: 'How do I track my order?',
                    answer: 'Open Track Order and enter your order number (for example ORD-20260917-ABC123). You can see the payment status and the courier and waybill number once the store has shipped your items.',
                    link: { label: 'Track an order', href: '/tracking' },
                },
                {
                    question: 'Something is wrong with my order. What should I do?',
                    answer: 'Contact us with your order number and a short description (photos help if an item arrived damaged). We will check with the store and get back to you.',
                },
            ],
        },
        {
            title: 'Account & reviews',
            items: [
                {
                    question: 'How do I save favorite products and follow stores?',
                    answer: 'Log in, then tap the heart on a product page or Follow on a store page. You can find them again under Favorite Products and Followed Stores in your profile.',
                    link: { label: 'Go to my profile', href: '/profile' },
                },
                {
                    question: 'When can I review a product?',
                    answer: 'You can rate and review a product once your order from that store has been delivered. Open Track Order and use the review button next to the item.',
                },
            ],
        },
        {
            title: 'Selling',
            items: [
                {
                    question: `How do I sell on ${siteName.value}?`,
                    answer: 'Log in, go to Seller Center and submit your store name and description. An admin reviews each application; once it is approved you can add products and manage orders in the Seller Dashboard.',
                    link: { label: 'Open Seller Center', href: sellerCenter().url },
                },
            ],
        },
    ];
});

const contactItems = computed<ContactItem[]>(() => {
    const items: ContactItem[] = [];
    const email = getSettingValue<string>('contact.email', '');
    const phone = getSettingValue<string>('contact.phone', '');
    const whatsapp = getSettingValue<string>('contact.whatsapp', '');
    const address = getSettingValue<string>('contact.address', '');
    const hours = getSettingValue<string>('contact.business_hours', '');
    const whatsappDigits = whatsapp.replace(/\D/g, '');

    if (whatsappDigits) items.push({ icon: MessageCircle, label: 'WhatsApp', value: whatsapp, href: `https://wa.me/${whatsappDigits}`, external: true });
    if (email) items.push({ icon: Mail, label: 'Email', value: email, href: `mailto:${email}` });
    if (phone) items.push({ icon: Phone, label: 'Phone', value: phone, href: `tel:${phone.replace(/[^\d+]/g, '')}` });
    if (hours) items.push({ icon: Clock, label: 'Business hours', value: hours });
    if (address) {
        items.push({
            icon: MapPin,
            label: 'Address',
            value: address,
            href: `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`,
            external: true,
        });
    }

    return items;
});
</script>

<template>
    <Head title="Help Center" />
    <MarketplaceLayout>
        <div class="mx-auto w-full max-w-[1200px] px-4 py-6 pb-24 md:pb-8">
            <nav class="mb-4 text-xs text-[var(--text-muted)]" aria-label="Breadcrumb">
                <Link href="/" class="hover:underline">Home</Link>
                <span class="mx-1">/</span>
                <span class="text-[var(--text-primary)]">Help Center</span>
            </nav>

            <section class="rounded-sm bg-[var(--brand-primary)] px-6 py-8 text-white shadow-[var(--shadow-card)]">
                <h1 class="text-2xl font-semibold">Help Center</h1>
                <p class="mt-1 max-w-xl text-sm text-white/90">
                    Answers to common questions about shopping on {{ siteName }}. Can't find what you need? Our team is
                    happy to help.
                </p>
            </section>

            <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_340px] lg:items-start">
                <div class="grid gap-4">
                    <section
                        v-for="group in faqGroups"
                        :key="group.title"
                        class="rounded-sm bg-white shadow-[var(--shadow-card)]"
                    >
                        <h2 class="border-b border-[var(--border-soft)] px-5 py-3 text-base font-semibold text-[var(--text-primary)]">
                            {{ group.title }}
                        </h2>
                        <details
                            v-for="faq in group.items"
                            :key="faq.question"
                            class="group border-b border-[var(--border-soft)] last:border-b-0"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-medium text-[var(--text-primary)] hover:text-[var(--brand-primary)] [&::-webkit-details-marker]:hidden"
                            >
                                {{ faq.question }}
                                <ChevronDown class="h-4 w-4 shrink-0 text-[var(--text-muted)] transition group-open:rotate-180" aria-hidden="true" />
                            </summary>
                            <div class="px-5 pb-4 text-sm leading-relaxed text-[var(--text-secondary)]">
                                <p>{{ faq.answer }}</p>
                                <Link
                                    v-if="faq.link"
                                    :href="faq.link.href"
                                    class="mt-2 inline-block font-medium text-[var(--brand-primary)] hover:underline"
                                >
                                    {{ faq.link.label }} →
                                </Link>
                            </div>
                        </details>
                    </section>
                </div>

                <aside class="rounded-sm bg-white p-5 shadow-[var(--shadow-card)] lg:sticky lg:top-24">
                    <h2 class="text-base font-semibold text-[var(--text-primary)]">Contact us</h2>
                    <p class="mt-1 text-xs text-[var(--text-muted)]">Include your order number so we can help faster.</p>
                    <ul v-if="contactItems.length" class="mt-4 grid gap-4">
                        <li v-for="item in contactItems" :key="item.label" class="flex items-start gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--brand-primary-soft)] text-[var(--brand-primary)]"
                                aria-hidden="true"
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 text-sm">
                                <p class="text-xs text-[var(--text-muted)]">{{ item.label }}</p>
                                <a
                                    v-if="item.href"
                                    :href="item.href"
                                    :target="item.external ? '_blank' : undefined"
                                    :rel="item.external ? 'noopener noreferrer' : undefined"
                                    class="break-words font-medium text-[var(--text-primary)] hover:text-[var(--brand-primary)] hover:underline"
                                >
                                    {{ item.value }}
                                </a>
                                <p v-else class="break-words font-medium text-[var(--text-primary)]">{{ item.value }}</p>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-[var(--text-muted)]">Contact details will be available soon.</p>
                </aside>
            </div>
        </div>
    </MarketplaceLayout>
</template>
