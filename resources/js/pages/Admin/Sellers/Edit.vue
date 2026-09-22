<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getMalaysiaCities } from '@/composables/useMalaysiaCities';
import { index, show } from '@/routes/admin/sellers';
import malaysiaStates from '@/data/malaysia-states.json';
import RichTextEditor from '@/components/RichTextEditor.vue';
import StoreMediaFields from '@/components/marketplace/StoreMediaFields.vue';
import { ArrowLeft } from '@lucide/vue';
import { toast } from 'vue-sonner';

type AdminSellerDetail = {
    id: number;
    store_name: string;
    slug: string;
    status: string;
    description?: string | null;
    profile_photo_url?: string | null;
    banner_url?: string | null;
    display_photo_url?: string | null;
    display_banner_url?: string | null;
    phone?: string | null;
    whatsapp?: string | null;
    store_location?: string | null;
    bank_account?: string | null;
    state?: string | null;
    city?: string | null;
};

const props = defineProps<{
    seller: AdminSellerDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Stores',
                href: index(),
            },
        ],
    },
});

const statuses = [
    { value: 'active', label: 'Active', hint: 'Store page is live and the owner can sell.' },
    { value: 'pending', label: 'Pending', hint: 'Waiting for approval; no seller access yet.' },
    { value: 'suspended', label: 'Suspended', hint: 'Store page and products hidden; the owner loses seller access.' },
];

const statusHint = computed(() => statuses.find((status) => status.value === form.status)?.hint ?? '');

const malaysiaStateOptions: string[] = (malaysiaStates as { name: string }[]).map(
    (stateOption) => stateOption.name,
);

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
    status: props.seller.status ?? 'pending',
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
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
            ?.content ?? ''
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
    formData.append('status', form.status);

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
        const response = await fetch(
            `/api/v1/admin/sellers/${props.seller.id}`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            },
        );

        const data = (await response.json()) as {
            errors?: Record<string, string[]>;
            message?: string;
        };

        if (!response.ok) {
            const first: Record<string, string> = {};
            for (const [field, messages] of Object.entries(data.errors ?? {})) {
                first[field] = messages[0] ?? 'Invalid value.';
            }
            errors.value = first;
            toast.error(data.message ?? 'Store could not be saved.');
            return;
        }

        toast.success('Store saved.');
        newPhoto.value = null;
        removePhoto.value = false;
        newBanner.value = null;
        removeBanner.value = false;

        router.reload({ only: ['seller'] });
    } catch {
        toast.error('Stores are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head :title="`Edit ${seller.store_name}`" />

    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <Link :href="show(seller.id)" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to store
        </Link>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading variant="small" :title="`Edit ${seller.store_name}`" :description="`/${seller.slug}`" />
            <div class="flex shrink-0 gap-2">
                <Link :href="show(seller.id)" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : 'Save store' }}</Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
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
                        :fallback-photo-url="seller.profile_photo_url ? null : seller.display_photo_url"
                        :fallback-banner-url="seller.banner_url ? null : seller.display_banner_url"
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
                        <InputError :message="errors.description" />
                    </div>
                </section>

                <section class="grid content-start gap-3 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Contact &amp; location</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="phone">Phone number</Label>
                            <Input id="phone" v-model="form.phone" type="tel" maxlength="30" />
                            <InputError :message="errors.phone" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="whatsapp">WhatsApp</Label>
                            <Input id="whatsapp" v-model="form.whatsapp" type="tel" maxlength="30" placeholder="e.g. 60123456789" />
                            <p class="text-xs text-muted-foreground">Include the country code so the Chat link works.</p>
                            <InputError :message="errors.whatsapp" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="state">State</Label>
                            <select id="state" v-model="form.state" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
                                <option value="">Select state</option>
                                <option v-for="stateOption in malaysiaStateOptions" :key="stateOption" :value="stateOption">{{ stateOption }}</option>
                            </select>
                            <InputError :message="errors.state" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="city">City</Label>
                            <select id="city" v-model="form.city" :disabled="form.state === ''" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm disabled:opacity-50">
                                <option value="">{{ form.state === '' ? 'Select a state first' : 'Select city' }}</option>
                                <option v-for="cityOption in cityOptions" :key="cityOption" :value="cityOption">{{ cityOption }}</option>
                            </select>
                            <InputError :message="errors.city" />
                        </div>
                    </div>
                    <div class="grid content-start gap-2">
                        <Label for="store_location">Address</Label>
                        <textarea id="store_location" v-model="form.store_location" rows="2" maxlength="500" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                        <InputError :message="errors.store_location" />
                    </div>
                </section>
            </div>

            <aside class="grid content-start gap-4">
                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <Label for="status" class="text-base font-medium">Status</Label>
                    <select id="status" v-model="form.status" class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                    <p class="text-xs text-muted-foreground">{{ statusHint }}</p>
                    <InputError :message="errors.status" />
                </section>

                <section class="grid content-start gap-2 rounded-xl border bg-card p-4 shadow-sm">
                    <Label for="bank_account" class="text-base font-medium">Payout account</Label>
                    <Input id="bank_account" v-model="form.bank_account" type="text" maxlength="255" placeholder="e.g. Maybank · Account name · 1234567890" />
                    <p class="text-xs text-muted-foreground">Where the marketplace pays this store. Only admins and the owner can see it.</p>
                    <InputError :message="errors.bank_account" />
                </section>
            </aside>
        </div>
    </form>
</template>
