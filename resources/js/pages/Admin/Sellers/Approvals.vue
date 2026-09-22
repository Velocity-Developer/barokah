<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index as sellerApprovalsIndex } from '@/routes/admin/seller-approvals';
import { approve, reject, show } from '@/routes/admin/sellers';

type SellerApplication = {
    id: number;
    store_name: string;
    slug: string;
    description: string | null;
    submitted_at: string | null;
    owner: {
        name: string | null;
        email: string | null;
        phone: string | null;
    };
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Seller approvals',
                href: sellerApprovalsIndex(),
            },
        ],
    },
});

defineProps<{
    applications: SellerApplication[];
}>();

const processingId = ref<number | null>(null);

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
        : '-';
}

function decide(application: SellerApplication, decision: 'approve' | 'reject'): void {
    if (
        decision === 'reject' &&
        !window.confirm(`Tolak pengajuan toko "${application.store_name}"? Pengguna dapat mengajukan lagi.`)
    ) {
        return;
    }

    const route = decision === 'approve' ? approve(application.id) : reject(application.id);

    router.post(route.url, {}, {
        preserveScroll: true,
        onStart: () => {
            processingId.value = application.id;
        },
        onFinish: () => {
            processingId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Seller approvals" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Heading
            variant="small"
            title="Seller approvals"
            description="Pengajuan toko dari pengguna. Toko baru bisa berjualan setelah disetujui."
        />

        <div class="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <p v-if="applications.length === 0" class="text-muted-foreground text-sm">
                Tidak ada pengajuan yang menunggu persetujuan.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="text-muted-foreground border-b font-medium">
                            <th class="px-3 py-2 font-medium">Toko</th>
                            <th class="px-3 py-2 font-medium">Pemilik</th>
                            <th class="px-3 py-2 font-medium">Diajukan</th>
                            <th class="px-3 py-2 text-right font-medium">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="application in applications"
                            :key="application.id"
                            class="border-b align-top last:border-0"
                        >
                            <td class="px-3 py-3">
                                <Link :href="show(application.id)" class="font-medium hover:underline">
                                    {{ application.store_name }}
                                </Link>
                                <p v-if="application.description" class="text-muted-foreground mt-1 line-clamp-2 max-w-md text-xs">
                                    {{ application.description }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <p>{{ application.owner.name ?? '-' }}</p>
                                <p class="text-muted-foreground text-xs">{{ application.owner.email }}</p>
                                <p v-if="application.owner.phone" class="text-muted-foreground text-xs">
                                    {{ application.owner.phone }}
                                </p>
                            </td>
                            <td class="text-muted-foreground px-3 py-3 whitespace-nowrap">
                                {{ formatDate(application.submitted_at) }}
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="processingId === application.id"
                                        @click="decide(application, 'reject')"
                                    >
                                        Tolak
                                    </Button>
                                    <Button
                                        size="sm"
                                        :disabled="processingId === application.id"
                                        @click="decide(application, 'approve')"
                                    >
                                        Setujui
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
