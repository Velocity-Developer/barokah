import { router } from '@inertiajs/vue3';
import { chat as sellerChat } from '@/routes/sellers';

export type ChatCounterpart = {
    name: string | null;
    photo_url: string | null;
    store_url: string | null;
    whatsapp_url: string | null;
};

export type ChatConversation = {
    id: number;
    role: 'buyer' | 'seller';
    counterpart: ChatCounterpart;
    store_name: string | null;
    last_message: { body: string; mine: boolean; created_at: string | null } | null;
    unread_count: number;
    last_message_at: string | null;
};

export type ChatProduct = {
    id: number;
    name: string;
    slug: string;
    price: string | number;
    image: string | null;
};

export type ChatMessage = {
    id: number;
    body: string;
    mine: boolean;
    created_at: string | null;
    product: ChatProduct | null;
};

/** The XSRF cookie stays valid after login/logout, unlike the csrf meta tag. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function request<T>(url: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(init.body ? { 'Content-Type': 'application/json' } : {}),
        },
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
        const firstError = Object.values(payload.errors ?? {})[0]?.[0];

        throw new Error(firstError ?? payload.message ?? `Request failed (${response.status}).`);
    }

    return (await response.json()) as T;
}

export function fetchConversations(include?: number | null): Promise<{ data: ChatConversation[]; unread_total: number }> {
    return request(`/chat/conversations${include ? `?include=${include}` : ''}`);
}

export function fetchMessages(
    conversationId: number,
    cursor: { after?: number; before?: number } = {},
): Promise<{ data: ChatMessage[]; has_older: boolean }> {
    const params = new URLSearchParams();

    if (cursor.after) params.set('after', String(cursor.after));
    if (cursor.before) params.set('before', String(cursor.before));

    const query = params.toString();

    return request(`/chat/conversations/${conversationId}/messages${query ? `?${query}` : ''}`);
}

export function sendMessage(conversationId: number, body: string, productId?: number | null): Promise<{ data: ChatMessage }> {
    return request(`/chat/conversations/${conversationId}/messages`, {
        method: 'POST',
        body: JSON.stringify({ body, product_id: productId ?? null }),
    });
}

/** Open (or create) the chat with a store, optionally about one of its products. */
export function openStoreChat(sellerSlug: string, productSlug?: string | null): void {
    router.post(sellerChat(sellerSlug, productSlug ? { query: { product: productSlug } } : undefined).url);
}
