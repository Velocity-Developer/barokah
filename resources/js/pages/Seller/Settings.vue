<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getMalaysiaCities } from '@/composables/useMalaysiaCities';
import malaysiaStates from '@/data/malaysia-states.json';
import RichTextEditor from '@/components/RichTextEditor.vue';
import StoreMediaFields from '@/components/marketplace/StoreMediaFields.vue';

type SellerSettingsDetail = {
    id: number;
    store_name: string;
    slug: string;
    status: string;
    description?: string | null;
    profile_photo_url?: string | null;
    banner_url?: string | null;
    owner_photo_url?: string | null;
    owner_banner_url?: string | null;
    phone?: string | null;
    whatsapp?: string | null;
    store_location?: string | null;
    bank_account?: string | null;
    state?: string | null;
    city?: string | null;
    public_url?: string | null;
};

const props = defineProps<{
    seller: SellerSettingsDetail;
}>();

const malaysiaStateOptions: string[] = (
    malaysiaStates as { name: string }[]
).map((stateOption) => stateOption.name);

const statusStyles: Record<string, string> = {
    active: 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-900',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
    suspended: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-900',
};

const statusNote: Record<string, string> = {
    active: 'Your store page is live and shoppers can buy from you.',
    pending: 'Waiting for the marketplace admin to approve your store.',
    suspended: 'Your store page and products are hidden. Contact the marketplace admin.',
};

const form = reactive({
    store_name: props.seller.store_name,
    slug: props.seller.slug,
    description: props.seller.description ?? '',
    phone: props.seller.phone ?? '',
    whatsapp: props.seller.whatsapp ?? '',
    store_location: props.seller.store_location ?? '',
    bank_account: props.seller.bank_account ?? '',
    state: props.seller.state ?? '',
    city: props.seller.city ?? '',
});

const cityOptions = computed(() =>
    form.state === '' ? [] : getMalaysiaCities(form.state),
);

watch(
    () => form.state,
    (nextState, prevState) => {
        if (nextState !== prevState) {
            form.city = '';
        }
    },
);

const errors = ref<Record<string, string>>({});
const isSaving = ref(false);
const newPhoto = ref<File | null>(null);
const removePhoto = ref(false);
const newBanner = ref<File | null>(null);
const removeBanner = ref(false);

function csrfToken(): string {
    return (
        (
            document.querySelector(
                'meta[name="csrf-token"]',
            ) as HTMLMetaElement | null
        )?.content ?? ''
    );
}

async function save(): Promise<void> {
    isSaving.value = true;
    errors.value = {};

    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('store_name', form.store_name.trim());
    formData.append('slug', form.slug.trim());
    formData.append('description', form.description.trim());
    formData.append('phone', form.phone.trim());
    formData.append('whatsapp', form.whatsapp.trim());
    formData.append('store_location', form.store_location.trim());
    formData.append('bank_account', form.bank_account.trim());
    formData.append('state', form.state);
    formData.append('city', form.city);

    if (newPhoto.value) {
        formData.append('profile_photo', newPhoto.value);
    }

    if (removePhoto.value) {
        formData.append('remove_profile_photo', '1');
    }

    if (newBanner.value) {
        formData.append('banner', newBanner.value);
    }

    if (removeBanner.value) {
        formData.append('remove_banner', '1');
    }

    try {
        const response = await fetch('/api/v1/seller/settings', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: formData,
        });

        const data = (await response.json()) as {
            errors?: Record<string, string[]>;
            message?: string;
        };

        if (!response.ok) {
            const first: Record<string, string> = {};
            for (const [field, messages] of Object.entries(
                data.errors ?? {},
            )) {
                first[field] = messages[0] ?? 'Invalid value.';
            }
            errors.value = first;
            toast.error(data.message ?? 'Store settings could not be saved.');
            return;
        }

        toast.success('Store settings saved.');
        newPhoto.value = null;
        removePhoto.value = false;
        newBanner.value = null;
        removeBanner.value = false;

        router.reload({ only: ['seller'] });
    } catch {
        toast.error('Store settings are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head title="Store settings" />

    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <Heading variant="small" title="Store settings" />
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusStyles[seller.status] ?? 'bg-muted text-muted-foreground ring-border'">
                        {{ seller.status }}
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">How your store looks to shoppers, and where you get paid.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a v-if="seller.public_url" :href="seller.public_url" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-sm font-medium hover:bg-muted">
                    <ExternalLink class="size-4" aria-hidden="true" /> View store
                </a>
                <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : 'Save changes' }}</Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="grid content-start gap-4">
                <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Store profile</h2>

                    <StoreMediaFields
                        v-model:photo="newPhoto"
                        v-model:remove-photo="removePhoto"
                        v-model:banner="newBanner"
                        v-model:remove-banner="removeBanner"
                        :store-name="form.store_name || seller.store_name"
                        :photo-url="seller.profile_photo_url"
                        :banner-url="seller.banner_url"
                        :fallback-photo-url="seller.profile_photo_url ? null : seller.owner_photo_url"
                        :fallback-banner-url="seller.banner_url ? null : seller.owner_banner_url"
                        :errors="errors"
                    />

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="store_name">Store name</Label>
                            <Input id="store_name" v-model="form.store_name" type="text" required maxlength="255" />
                            <InputError :message="errors.store_name" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="slug">Store URL</Label>
                            <div class="flex h-9 items-center overflow-hidden rounded-md border border-input shadow-xs focus-within:ring-2 focus-within:ring-ring/30">
                                <span class="shrink-0 pl-3 text-sm text-muted-foreground">/sellers/</span>
                                <input id="slug" v-model="form.slug" maxlength="255" class="h-full min-w-0 flex-1 bg-transparent pr-3 text-sm outline-none" />
                            </div>
                            <p class="text-xs text-muted-foreground">Leave empty to generate it from the store name.</p>
                            <InputError :message="errors.slug" />
                        </div>
                    </div>

                    <div class="grid content-start gap-2">
                        <Label for="description">Description</Label>
                        <RichTextEditor v-model="form.description" />
                        <p class="text-xs text-muted-foreground">Shown on your store page. Tell shoppers what you sell.</p>
                        <InputError :message="errors.description" />
                    </div>
                </section>

                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Contact &amp; location</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="phone">Phone number</Label>
                            <Input id="phone" v-model="form.phone" type="text" placeholder="03-55123456" />
                            <InputError :message="errors.phone" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="whatsapp">WhatsApp</Label>
                            <Input id="whatsapp" v-model="form.whatsapp" type="text" placeholder="60123456789" />
                            <p class="text-xs text-muted-foreground">Include the country code so the Chat link works.</p>
                            <InputError :message="errors.whatsapp" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="state">State</Label>
                            <select id="state" v-model="form.state" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="">No state</option>
                                <option v-for="stateOption in malaysiaStateOptions" :key="stateOption" :value="stateOption">{{ stateOption }}</option>
                            </select>
                            <InputError :message="errors.state" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="city">City</Label>
                            <select
                                id="city"
                                v-model="form.city"
                                :disabled="form.state === '' || cityOptions.length === 0"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-50"
                            >
                                <option value="">
                                    {{ form.state === '' ? 'Select state first' : cityOptions.length === 0 ? 'No cities available' : 'No city' }}
                                </option>
                                <option v-for="cityOption in cityOptions" :key="cityOption" :value="cityOption">{{ cityOption }}</option>
                            </select>
                            <InputError :message="errors.city" />
                        </div>
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="store_location">Address</Label>
                        <textarea
                            id="store_location"
                            v-model="form.store_location"
                            rows="2"
                            maxlength="500"
                            placeholder="No. 12, Jalan Meru, Klang, Selangor"
                            class="min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:outline-none"
                        />
                        <InputError :message="errors.store_location" />
                    </div>
                </section>
            </div>

            <div class="grid content-start gap-4">
                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Store status</h2>
                    <p class="text-sm text-muted-foreground">{{ statusNote[seller.status] ?? seller.status }}</p>
                    <p class="text-xs text-muted-foreground">Only the marketplace admin can change this.</p>
                </section>

                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Payout account</h2>
                    <Label for="bank_account" class="sr-only">Bank account</Label>
                    <Input id="bank_account" v-model="form.bank_account" type="text" placeholder="Maybank a.n. Nama Pemilik Rekening 1234567890" />
                    <p class="text-xs text-muted-foreground">Where the marketplace pays you. Only you and the admin can see it.</p>
                    <InputError :message="errors.bank_account" />
                </section>
            </div>
        </div>
    </form>
</template>
