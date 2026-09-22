<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle, Send, Store, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { ChatConversation, ChatMessage, ChatProduct } from '@/composables/useChatApi';
import { fetchConversations, fetchMessages, sendMessage } from '@/composables/useChatApi';
import { useSettingsStore } from '@/stores/settings';

const props = defineProps<{
    /** Poll only while the Messages tab is on screen. */
    active: boolean;
}>();

const LIST_POLL_MS = 15000;
const THREAD_POLL_MS = 5000;

const page = usePage();
const { formatAmount } = useSettingsStore();

const conversations = ref<ChatConversation[]>([]);
const listLoaded = ref(false);
const listError = ref<string | null>(null);
const selectedId = ref<number | null>(null);
const messages = ref<ChatMessage[]>([]);
const hasOlder = ref(false);
const threadLoading = ref(false);
const loadingOlder = ref(false);
const draft = ref('');
const sending = ref(false);
const sendError = ref<string | null>(null);
const attachedProduct = ref<ChatProduct | null>(null);
const messagesBox = ref<HTMLElement | null>(null);

let listTimer: ReturnType<typeof setInterval> | null = null;
let threadTimer: ReturnType<typeof setInterval> | null = null;

const selected = computed<ChatConversation | null>(
    () => conversations.value.find((conversation) => conversation.id === selectedId.value) ?? null,
);

function urlParam(name: string): string | null {
    return new URLSearchParams(page.url.split('?')[1] ?? '').get(name);
}

function formatTime(value: string | null): string {
    if (!value) return '';

    const date = new Date(value);
    const today = new Date();

    return date.toDateString() === today.toDateString()
        ? date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function initial(name: string | null): string {
    return (name ?? '?').charAt(0).toUpperCase();
}

/** Refresh the unread badge in the header after reading. */
function refreshSharedUnread(): void {
    router.reload({ only: ['auth'] });
}

async function loadConversations(): Promise<void> {
    try {
        const response = await fetchConversations(selectedId.value);
        conversations.value = response.data;
        listError.value = null;
    } catch (error) {
        listError.value = error instanceof Error ? error.message : 'Messages are unavailable right now.';
    } finally {
        listLoaded.value = true;
    }
}

function scrollToBottom(): void {
    void nextTick(() => {
        if (messagesBox.value) {
            messagesBox.value.scrollTop = messagesBox.value.scrollHeight;
        }
    });
}

async function openConversation(id: number): Promise<void> {
    const hadUnread = (conversations.value.find((conversation) => conversation.id === id)?.unread_count ?? 0) > 0;

    selectedId.value = id;
    messages.value = [];
    hasOlder.value = false;
    sendError.value = null;
    threadLoading.value = true;

    try {
        const response = await fetchMessages(id);
        messages.value = response.data;
        hasOlder.value = response.has_older;
        const conversation = conversations.value.find((item) => item.id === id);

        if (conversation) conversation.unread_count = 0;
        if (hadUnread) refreshSharedUnread();
        scrollToBottom();
    } catch (error) {
        sendError.value = error instanceof Error ? error.message : 'Messages could not be loaded.';
    } finally {
        threadLoading.value = false;
    }
}

async function pollThread(): Promise<void> {
    if (selectedId.value === null || threadLoading.value) return;

    const lastId = messages.value.at(-1)?.id ?? 0;
    const box = messagesBox.value;
    const nearBottom = box ? box.scrollHeight - box.scrollTop - box.clientHeight < 80 : true;

    try {
        const response = await fetchMessages(selectedId.value, { after: lastId });

        if (response.data.length) {
            const known = new Set(messages.value.map((message) => message.id));
            messages.value.push(...response.data.filter((message) => !known.has(message.id)));
            if (nearBottom) scrollToBottom();
        }
    } catch {
        // Keep the thread as is; the next poll retries.
    }
}

async function loadOlder(): Promise<void> {
    const first = messages.value[0];

    if (!first || selectedId.value === null) return;

    loadingOlder.value = true;
    const box = messagesBox.value;
    const previousHeight = box?.scrollHeight ?? 0;

    try {
        const response = await fetchMessages(selectedId.value, { before: first.id });
        messages.value.unshift(...response.data);
        hasOlder.value = response.has_older;
        void nextTick(() => {
            if (box) box.scrollTop = box.scrollHeight - previousHeight;
        });
    } finally {
        loadingOlder.value = false;
    }
}

async function submit(): Promise<void> {
    const body = draft.value.trim();

    if (!body || selectedId.value === null || sending.value) return;

    sending.value = true;
    sendError.value = null;

    try {
        const response = await sendMessage(selectedId.value, body, attachedProduct.value?.id);
        messages.value.push(response.data);
        draft.value = '';
        attachedProduct.value = null;
        scrollToBottom();
        void loadConversations();
    } catch (error) {
        sendError.value = error instanceof Error ? error.message : 'Message could not be sent.';
    } finally {
        sending.value = false;
    }
}

function onComposerKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        void submit();
    }
}

/** A chat opened from a product page carries that product into the composer. */
async function loadAttachedProduct(slug: string): Promise<void> {
    try {
        const response = await fetch(`/api/v1/products/${encodeURIComponent(slug)}`, { headers: { Accept: 'application/json' } });

        if (!response.ok) return;

        const product = ((await response.json()) as { data: { id: number; name: string; slug: string; price: string | number; primary_image?: string | null } }).data;
        attachedProduct.value = { id: product.id, name: product.name, slug: product.slug, price: product.price, image: product.primary_image ?? null };
    } catch {
        // The chat still works without the product preview.
    }
}

function stopPolling(): void {
    if (listTimer) clearInterval(listTimer);
    if (threadTimer) clearInterval(threadTimer);
    listTimer = null;
    threadTimer = null;
}

function startPolling(): void {
    stopPolling();

    if (!props.active || document.visibilityState !== 'visible') return;

    listTimer = setInterval(() => void loadConversations(), LIST_POLL_MS);
    threadTimer = setInterval(() => void pollThread(), THREAD_POLL_MS);
}

function onVisibilityChange(): void {
    if (document.visibilityState === 'visible' && props.active) {
        void loadConversations();
        void pollThread();
    }
    startPolling();
}

watch(
    () => props.active,
    (active) => {
        if (active) {
            void loadConversations();
            void pollThread();
        }
        startPolling();
    },
);

onMounted(async () => {
    const requested = Number(urlParam('conversation'));
    const product = urlParam('product');

    if (requested) selectedId.value = requested;
    if (product) void loadAttachedProduct(product);

    await loadConversations();

    if (requested && conversations.value.some((conversation) => conversation.id === requested)) {
        await openConversation(requested);
    } else {
        selectedId.value = null;
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    startPolling();
});

onBeforeUnmount(() => {
    stopPolling();
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <div class="grid min-h-[520px] min-w-0 grid-cols-1 overflow-hidden rounded-md border border-gray-100 md:grid-cols-[280px_minmax(0,1fr)]">
        <!-- Conversation list -->
        <aside
            class="min-w-0 border-gray-100 md:border-r"
            :class="selectedId !== null ? 'hidden md:block' : ''"
            aria-label="Conversations"
        >
            <p v-if="!listLoaded" class="p-4 text-sm text-gray-500">Loading conversations...</p>
            <p v-else-if="listError" class="p-4 text-sm text-red-600">{{ listError }}</p>
            <div v-else-if="conversations.length === 0" class="flex flex-col items-center gap-2 px-6 py-16 text-center">
                <MessageCircle class="h-8 w-8 text-gray-300" aria-hidden="true" />
                <p class="text-sm font-medium text-gray-700">No messages yet</p>
                <p class="text-xs text-gray-500">Tap Chat on a store or product page to ask the seller a question.</p>
            </div>
            <ul v-else class="max-h-[640px] divide-y divide-gray-100 overflow-y-auto">
                <li v-for="conversation in conversations" :key="conversation.id">
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-gray-50"
                        :class="conversation.id === selectedId ? 'bg-gray-50' : ''"
                        @click="openConversation(conversation.id)"
                    >
                        <img
                            v-if="conversation.counterpart.photo_url"
                            :src="conversation.counterpart.photo_url"
                            alt=""
                            class="size-10 shrink-0 rounded-full object-cover"
                        />
                        <span
                            v-else
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--accent-navy)] text-sm font-semibold text-white"
                            aria-hidden="true"
                        >
                            {{ initial(conversation.counterpart.name) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-gray-900">{{ conversation.counterpart.name }}</span>
                                <span class="shrink-0 text-[11px] text-gray-400">{{ formatTime(conversation.last_message_at) }}</span>
                            </span>
                            <span v-if="conversation.role === 'seller'" class="block truncate text-[11px] text-[var(--brand-primary)]">
                                Customer of {{ conversation.store_name }}
                            </span>
                            <span class="mt-0.5 flex items-center justify-between gap-2">
                                <span class="truncate text-xs" :class="conversation.unread_count ? 'font-medium text-gray-900' : 'text-gray-500'">
                                    <template v-if="conversation.last_message">
                                        {{ conversation.last_message.mine ? 'You: ' : '' }}{{ conversation.last_message.body }}
                                    </template>
                                    <template v-else>New conversation</template>
                                </span>
                                <span
                                    v-if="conversation.unread_count"
                                    class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-[11px] font-semibold text-white"
                                    style="background-color: var(--brand-primary)"
                                >
                                    {{ conversation.unread_count }}
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            </ul>
        </aside>

        <!-- Thread -->
        <section class="flex min-h-[520px] min-w-0 flex-col" :class="selectedId === null ? 'hidden md:flex' : ''" aria-label="Conversation">
            <div v-if="selected === null" class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center text-sm text-gray-500">
                <MessageCircle class="h-8 w-8 text-gray-300" aria-hidden="true" />
                Select a conversation to read and reply.
            </div>

            <template v-else>
                <header class="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
                    <button type="button" class="-ml-1 rounded p-1 text-gray-500 hover:bg-gray-100 md:hidden" aria-label="Back to conversations" @click="selectedId = null">
                        <ArrowLeft class="h-5 w-5" />
                    </button>
                    <img v-if="selected.counterpart.photo_url" :src="selected.counterpart.photo_url" alt="" class="size-9 rounded-full object-cover" />
                    <span v-else class="flex size-9 items-center justify-center rounded-full bg-[var(--accent-navy)] text-sm font-semibold text-white" aria-hidden="true">
                        {{ initial(selected.counterpart.name) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ selected.counterpart.name }}</p>
                        <p class="truncate text-xs text-gray-500">
                            {{ selected.role === 'seller' ? `Customer of ${selected.store_name}` : 'Store' }}
                        </p>
                    </div>
                    <Link
                        v-if="selected.counterpart.store_url"
                        :href="selected.counterpart.store_url"
                        class="hidden items-center gap-1 rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 sm:inline-flex"
                    >
                        <Store class="h-3.5 w-3.5" aria-hidden="true" /> Visit store
                    </Link>
                    <a
                        v-if="selected.counterpart.whatsapp_url"
                        :href="selected.counterpart.whatsapp_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1 rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50"
                    >
                        WhatsApp
                    </a>
                </header>

                <div ref="messagesBox" class="flex-1 space-y-3 overflow-y-auto bg-gray-50/60 px-4 py-4" style="max-height: 460px">
                    <div v-if="hasOlder" class="text-center">
                        <button type="button" class="text-xs font-medium text-[var(--brand-primary)] hover:underline disabled:opacity-60" :disabled="loadingOlder" @click="loadOlder">
                            {{ loadingOlder ? 'Loading...' : 'Load earlier messages' }}
                        </button>
                    </div>
                    <p v-if="threadLoading" class="text-center text-xs text-gray-500">Loading messages...</p>
                    <p v-else-if="messages.length === 0" class="py-10 text-center text-xs text-gray-500">
                        Say hello — ask about stock, sizes or delivery.
                    </p>
                    <div v-for="message in messages" :key="message.id" class="flex" :class="message.mine ? 'justify-end' : 'justify-start'">
                        <div
                            class="min-w-0 max-w-[80%] rounded-lg px-3 py-2 text-sm shadow-sm"
                            :class="message.mine ? 'rounded-br-sm bg-[var(--brand-primary)] text-white' : 'rounded-bl-sm bg-white text-gray-800'"
                        >
                            <Link
                                v-if="message.product"
                                :href="`/products/${message.product.slug}`"
                                class="mb-2 flex items-center gap-2 rounded-md bg-white/95 p-1.5 text-gray-800"
                            >
                                <img v-if="message.product.image" :src="message.product.image" alt="" class="size-10 rounded object-cover" />
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-medium">{{ message.product.name }}</span>
                                    <span class="block text-xs text-[var(--brand-primary)]">{{ formatAmount(Number(message.product.price)) }}</span>
                                </span>
                            </Link>
                            <p class="whitespace-pre-line break-words">{{ message.body }}</p>
                            <p class="mt-1 text-right text-[10px]" :class="message.mine ? 'text-white/75' : 'text-gray-400'">
                                {{ formatTime(message.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>

                <form class="border-t border-gray-100 p-3" @submit.prevent="submit">
                    <div v-if="attachedProduct" class="mb-2 flex items-center gap-2 rounded-md border border-gray-200 p-2">
                        <img v-if="attachedProduct.image" :src="attachedProduct.image" alt="" class="size-9 rounded object-cover" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-xs font-medium text-gray-800">Asking about: {{ attachedProduct.name }}</span>
                            <span class="block text-xs text-[var(--brand-primary)]">{{ formatAmount(Number(attachedProduct.price)) }}</span>
                        </span>
                        <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100" aria-label="Remove product" @click="attachedProduct = null">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex items-end gap-2">
                        <textarea
                            v-model="draft"
                            rows="2"
                            maxlength="2000"
                            placeholder="Write a message..."
                            aria-label="Message"
                            class="min-h-11 flex-1 resize-none rounded-md border border-gray-200 px-3 py-2 text-sm outline-none focus:border-[var(--brand-primary)] focus:ring-2 focus:ring-[var(--brand-primary)]/20"
                            @keydown="onComposerKeydown"
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="sending || draft.trim() === ''"
                            class="inline-flex h-11 items-center gap-1.5 rounded-md px-4 text-sm font-semibold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
                            style="background-color: var(--brand-primary)"
                        >
                            <Send class="h-4 w-4" aria-hidden="true" />
                            Send
                        </button>
                    </div>
                    <p v-if="sendError" class="mt-2 text-xs text-red-600">{{ sendError }}</p>
                    <p class="mt-1 text-[11px] text-gray-400">Enter to send, Shift + Enter for a new line.</p>
                </form>
            </template>
        </section>
    </div>
</template>
