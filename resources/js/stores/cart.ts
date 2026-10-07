import { computed, reactive, watch } from 'vue';

export type CartItem = {
    productId: number;
    /** Chosen variant of a product with variations; null otherwise. */
    variantId: number | null;
    variantLabel: string | null;
    slug: string;
    name: string;
    price: number;
    image: string | null;
    stock: number;
    quantity: number;
};

const storageKey = 'barokah.cart';
const state = reactive<{ items: CartItem[] }>({ items: [] });
let hydrated = false;

function hydrate(): void {
    if (hydrated || typeof window === 'undefined') {
        return;
    }

    hydrated = true;
    try {
        const stored = JSON.parse(window.localStorage.getItem(storageKey) ?? '[]');
        if (Array.isArray(stored)) {
            // Carts saved before variations existed have no variant fields.
            state.items = stored.map((item: CartItem) => ({ ...item, variantId: item.variantId ?? null, variantLabel: item.variantLabel ?? null }));
        }
    } catch {
        state.items = [];
    }
}

watch(() => state.items, (items) => {
    if (hydrated && typeof window !== 'undefined') {
        window.localStorage.setItem(storageKey, JSON.stringify(items));
    }
}, { deep: true });

/** One cart line per product and variant: "12" or "12:34". */
export function cartLineKey(item: Pick<CartItem, 'productId' | 'variantId'>): string {
    return item.variantId === null ? String(item.productId) : `${item.productId}:${item.variantId}`;
}

export function useCartStore() {
    hydrate();

    function find(key: string): CartItem | undefined {
        return state.items.find((entry) => cartLineKey(entry) === key);
    }

    function add(item: Omit<CartItem, 'quantity'>, quantity = 1): void {
        const existing = find(cartLineKey(item));
        if (existing) {
            existing.quantity = Math.min(existing.quantity + quantity, existing.stock);
            return;
        }
        state.items.push({ ...item, quantity: Math.min(quantity, item.stock) });
    }

    function update(key: string, quantity: number): void {
        const item = find(key);
        if (!item) return;
        item.quantity = Math.max(1, Math.min(quantity, item.stock));
    }

    function remove(key: string): void {
        state.items = state.items.filter((item) => cartLineKey(item) !== key);
    }

    function clear(): void {
        state.items = [];
    }

    const count = computed(() => state.items.reduce((sum, item) => sum + item.quantity, 0));
    const subtotal = computed(() => state.items.reduce((sum, item) => sum + item.price * item.quantity, 0));

    return { state, count, subtotal, add, update, remove, clear };
}
