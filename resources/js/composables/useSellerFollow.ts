import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import { follow as sellerFollow } from '@/routes/sellers';

type FollowableSeller = {
    slug: string;
    is_followed?: boolean;
    followers_count?: number;
};

export type UseSellerFollowReturn = {
    isLoggedIn: ComputedRef<boolean>;
    isFollowed: Ref<boolean>;
    followersCount: Ref<number>;
    followProcessing: Ref<boolean>;
    toggleFollow: () => void;
};

/**
 * Follow / unfollow a store with an optimistic update that is rolled back
 * if the request fails. State resyncs whenever the page sends fresh props.
 */
export function useSellerFollow(seller: Ref<FollowableSeller | null | undefined>): UseSellerFollowReturn {
    const page = usePage();
    const isLoggedIn = computed<boolean>(() => Boolean(page.props.auth?.user));
    const isFollowed = ref<boolean>(seller.value?.is_followed ?? false);
    const followersCount = ref<number>(seller.value?.followers_count ?? 0);
    const followProcessing = ref(false);

    watch(seller, (value) => {
        isFollowed.value = value?.is_followed ?? false;
        followersCount.value = value?.followers_count ?? 0;
    });

    function toggleFollow(): void {
        if (followProcessing.value || !seller.value) {
            return;
        }

        const previous = { followed: isFollowed.value, count: followersCount.value };
        isFollowed.value = !previous.followed;
        followersCount.value = Math.max(0, previous.count + (previous.followed ? -1 : 1));

        router.post(sellerFollow(seller.value.slug).url, {}, {
            preserveScroll: true,
            onStart: () => {
                followProcessing.value = true;
            },
            onError: () => {
                isFollowed.value = previous.followed;
                followersCount.value = previous.count;
            },
            onFinish: () => {
                followProcessing.value = false;
            },
        });
    }

    return { isLoggedIn, isFollowed, followersCount, followProcessing, toggleFollow };
}

/** WhatsApp link for a store, using its WhatsApp number or phone. */
export function sellerWhatsappLink(seller: { whatsapp?: string | null; phone?: string | null } | null | undefined): string | null {
    const number = (seller?.whatsapp || seller?.phone || '').replace(/\D/g, '');

    return number ? `https://wa.me/${number}` : null;
}
