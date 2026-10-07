/**
 * Product variations as the server sends them (ProductVariationsResource)
 * and as the product forms edit them. Shared by the seller and admin forms,
 * the product page picker and checkout.
 */

export type VariationOption = { id: number; name: string; image_url: string | null };

export type VariationType = { name: string; options: VariationOption[] };

export type Variant = {
    id: number;
    /** Position of the chosen option in each type, e.g. [1, 0]. */
    options: number[];
    label: string;
    price: string;
    effective_price: string;
    stock: number;
    weight_grams: number | null;
};

export type ProductVariations = { types: VariationType[]; variants: Variant[] };

export const MAX_TYPES = 2;
export const MAX_OPTIONS = 20;

export type OptionDraft = {
    key: string;
    id: number | null;
    name: string;
    imageUrl: string | null;
    newImage: File | null;
    removeImage: boolean;
};

export type TypeDraft = { key: string; name: string; options: OptionDraft[] };

export type VariantDraft = { price: string; stock: string; weight_grams: string };

/**
 * Editable variations. `variants` is keyed by the option keys it combines
 * ("o3" or "o3|o7") so typed prices survive adding or renaming options.
 */
export type VariationDraft = {
    enabled: boolean;
    /** The product had variations when the form opened. */
    hadVariations: boolean;
    types: TypeDraft[];
    variants: Record<string, VariantDraft>;
};

let keySeed = 0;

export function draftKey(): string {
    keySeed += 1;

    return `o${keySeed}`;
}

export function emptyOption(): OptionDraft {
    return { key: draftKey(), id: null, name: '', imageUrl: null, newImage: null, removeImage: false };
}

export function emptyType(): TypeDraft {
    return { key: draftKey(), name: '', options: [emptyOption()] };
}

/**
 * Draft for the form, from the saved variations (or an empty, switched off
 * draft for a product without them).
 */
export function draftFrom(saved: ProductVariations | null | undefined): VariationDraft {
    if (!saved || saved.types.length === 0) {
        return { enabled: false, hadVariations: false, types: [], variants: {} };
    }

    const types: TypeDraft[] = saved.types.map((type) => ({
        key: draftKey(),
        name: type.name,
        options: type.options.map((option) => ({
            key: draftKey(),
            id: option.id,
            name: option.name,
            imageUrl: option.image_url,
            newImage: null,
            removeImage: false,
        })),
    }));

    const variants: Record<string, VariantDraft> = {};
    for (const variant of saved.variants) {
        const key = variant.options.map((position, level) => types[level]?.options[position]?.key).join('|');
        variants[key] = {
            price: String(variant.price),
            stock: String(variant.stock),
            weight_grams: variant.weight_grams === null ? '' : String(variant.weight_grams),
        };
    }

    return { enabled: true, hadVariations: true, types, variants };
}

export type VariantRow = { key: string; positions: number[]; options: OptionDraft[] };

/** Every combination of options, first type outermost (Black/M, Black/L, Navy/M…). */
export function variantRows(types: TypeDraft[]): VariantRow[] {
    return types.reduce<VariantRow[]>(
        (rows, type, level) =>
            rows.flatMap((row) =>
                type.options.map((option, position) => ({
                    key: level === 0 ? option.key : `${row.key}|${option.key}`,
                    positions: [...row.positions, position],
                    options: [...row.options, option],
                })),
            ),
        [{ key: '', positions: [], options: [] }],
    );
}

/** Give every current combination a row of values to edit. */
export function ensureVariants(draft: VariationDraft): void {
    for (const row of variantRows(draft.types)) {
        draft.variants[row.key] ??= { price: '', stock: '', weight_grams: '' };
    }
}

function variantFor(draft: VariationDraft, key: string): VariantDraft {
    return draft.variants[key] ?? { price: '', stock: '', weight_grams: '' };
}

/** Cheapest price and total stock, mirrored into the product's own fields. */
export function draftTotals(draft: VariationDraft): { price: string; stock: string } {
    const rows = variantRows(draft.types).map((row) => variantFor(draft, row.key));
    const prices = rows.map((row) => Number(row.price)).filter((price) => Number.isFinite(price) && price > 0);

    return {
        price: prices.length ? String(Math.min(...prices)) : '0',
        stock: String(rows.reduce((sum, row) => sum + (Number(row.stock) || 0), 0)),
    };
}

/**
 * Put the variations into the multipart form body: the JSON the server
 * expects plus one file per new first-type option photo.
 */
export function appendVariations(data: FormData, draft: VariationDraft): void {
    if (!draft.enabled) {
        if (draft.hadVariations) data.append('variations', JSON.stringify({ types: [], variants: [] }));

        return;
    }

    const types = draft.types.map((type) => ({
        name: type.name.trim(),
        options: type.options.map((option) => ({ id: option.id, name: option.name.trim(), remove_image: option.removeImage && !option.newImage })),
    }));
    const variants = variantRows(draft.types).map((row) => {
        const values = variantFor(draft, row.key);

        return {
            options: row.positions,
            price: values.price,
            stock: values.stock,
            weight_grams: values.weight_grams === '' ? null : values.weight_grams,
        };
    });

    data.append('variations', JSON.stringify({ types, variants }));
    draft.types[0]?.options.forEach((option, position) => {
        if (option.newImage) data.append(`variation_images[${position}]`, option.newImage);
    });
}

/** First message among the variation errors the server sent back. */
export function variationError(errors: Record<string, string>): string | undefined {
    return Object.entries(errors).find(([field]) => field === 'variations' || field.startsWith('payload') || field.startsWith('variation_images'))?.[1];
}
