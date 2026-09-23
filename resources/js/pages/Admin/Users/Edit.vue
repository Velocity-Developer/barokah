<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getMalaysiaCities } from '@/composables/useMalaysiaCities';
import { index, show } from '@/routes/admin/users';
import { show as sellerShow } from '@/routes/admin/sellers';
import malaysiaStates from '@/data/malaysia-states.json';

type AdminUserDetail = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    address?: string | null;
    state?: string | null;
    city?: string | null;
    post_code?: string | null;
    email_verified_at?: string | null;
    is_admin: boolean;
    is_active_as_seller: boolean;
    seller?: { id: number; store_name: string; status: string } | null;
};

const props = defineProps<{
    user: AdminUserDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Customers',
                href: index(),
            },
        ],
    },
});

const malaysiaStateOptions: string[] = (malaysiaStates as { name: string }[]).map(
    (stateOption) => stateOption.name,
);

const form = reactive({
    name: props.user.name,
    email: props.user.email,
    phone: props.user.phone ?? '',
    address: props.user.address ?? '',
    state: props.user.state ?? '',
    city: props.user.city ?? '',
    post_code: props.user.post_code ?? '',
    is_admin: props.user.is_admin,
    is_active_as_seller: props.user.is_active_as_seller,
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

const page = usePage();
// Admins can edit themselves; warn before they drop their own admin access.
const isSelf = computed(() => (page.props.auth as { user?: { id?: number } } | undefined)?.user?.id === props.user.id);
const losesOwnAdmin = computed(() => isSelf.value && props.user.is_admin && !form.is_admin);

function csrfToken(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
            ?.content ?? ''
    );
}

async function save(): Promise<void> {
    if (losesOwnAdmin.value && !window.confirm('Remove your own admin access? You will lose the admin area straight away.')) {
        return;
    }

    isSaving.value = true;
    errors.value = {};

    try {
        const response = await fetch(`/api/v1/admin/users/${props.user.id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                name: form.name,
                email: form.email,
                phone: form.phone.trim() === '' ? null : form.phone,
                address: form.address.trim() === '' ? null : form.address,
                state: form.state === '' ? null : form.state,
                city: form.city === '' ? null : form.city,
                post_code: form.post_code.trim() === '' ? null : form.post_code,
                is_admin: form.is_admin,
                is_active_as_seller: form.is_active_as_seller,
            }),
        });

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
            toast.error(data.message ?? 'Customer could not be saved.');
            return;
        }

        toast.success('Customer saved.');

        if (losesOwnAdmin.value) {
            window.location.href = '/';

            return;
        }
    } catch {
        toast.error('Customers are temporarily unavailable.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <form class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6" @submit.prevent="save">
        <Link :href="show(user.id)" class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" aria-hidden="true" /> Back to customer
        </Link>

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <Heading variant="small" :title="`Edit ${user.name}`" :description="user.email" />
            <div class="flex shrink-0 gap-2">
                <Link :href="show(user.id)" class="inline-flex h-9 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Cancel</Link>
                <Button :disabled="isSaving" type="submit">{{ isSaving ? 'Saving…' : 'Save customer' }}</Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid content-start gap-4">
                <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Account</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" type="text" required maxlength="255" />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="form.email" type="email" required />
                            <p class="text-xs text-muted-foreground">
                                {{ user.email_verified_at ? 'Changing the email marks it unverified again.' : 'This email is not verified yet.' }}
                            </p>
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="phone">Phone number</Label>
                            <Input id="phone" v-model="form.phone" type="text" placeholder="012-3456789" />
                            <InputError :message="errors.phone" />
                        </div>
                    </div>
                </section>

                <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Delivery address</h2>
                    <div class="grid content-start gap-2">
                        <Label for="address">Street address</Label>
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="3"
                            maxlength="500"
                            class="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring/30 focus-visible:outline-none"
                        />
                        <p class="text-xs text-muted-foreground">Used to prefill checkout for this customer.</p>
                        <InputError :message="errors.address" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
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
                        <div class="grid content-start gap-2">
                            <Label for="post_code">Post code</Label>
                            <Input id="post_code" v-model="form.post_code" type="text" maxlength="20" placeholder="50000" />
                            <InputError :message="errors.post_code" />
                        </div>
                    </div>
                </section>
            </div>

            <div class="grid content-start gap-4">
                <section class="grid content-start gap-4 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Access</h2>

                    <label class="flex items-start gap-3" for="is_admin">
                        <input
                            id="is_admin"
                            v-model="form.is_admin"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="grid gap-0.5">
                            <span class="text-sm font-medium">Admin</span>
                            <span class="text-xs text-muted-foreground">Full access to this admin area, including orders, payments and settings.</span>
                        </span>
                    </label>
                    <InputError :message="errors.is_admin" />

                    <label class="flex items-start gap-3 border-t pt-4" for="is_active_as_seller">
                        <input
                            id="is_active_as_seller"
                            v-model="form.is_active_as_seller"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="grid gap-0.5">
                            <span class="text-sm font-medium">Seller access</span>
                            <span class="text-xs text-muted-foreground">
                                Lets the owner open the seller dashboard. The store page itself follows the store status.
                            </span>
                        </span>
                    </label>
                    <InputError :message="errors.is_active_as_seller" />

                    <p v-if="losesOwnAdmin" class="rounded-md bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        This is your own account. Saving without admin closes the admin area for you.
                    </p>
                </section>

                <section v-if="user.seller" class="grid content-start gap-1 rounded-xl border bg-card p-4 shadow-sm">
                    <h2 class="text-base font-medium">Store</h2>
                    <Link :href="sellerShow(user.seller.id)" class="text-sm font-medium hover:underline">{{ user.seller.store_name }}</Link>
                    <p class="text-xs text-muted-foreground">Status: {{ user.seller.status }}. Store details are edited on the store page.</p>
                </section>
            </div>
        </div>
    </form>
</template>
