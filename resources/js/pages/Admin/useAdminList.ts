export type PaginatedLinks = { url: string | null; label: string; active: boolean }[];
export type PaginatedMeta = { current_page: number; last_page: number; total: number; per_page: number; from: number; to: number };

export type PaginatedResponse<T> = {
    data: T[];
    links: PaginatedLinks;
    meta: PaginatedMeta;
};

export async function fetchAdminList<T>(url: string): Promise<T[]> {
    const paginated = await fetchAdminPaginated<T>(url, 1);

    return paginated.data;
}

type RawLinks = { url: string | null; label: string; active: boolean }[];
type RawMeta = PaginatedMeta & { links?: RawLinks };

function normalizeMeta(meta: RawMeta | undefined): PaginatedMeta {
    return {
        current_page: meta?.current_page ?? 1,
        last_page: meta?.last_page ?? 1,
        total: meta?.total ?? 0,
        per_page: meta?.per_page ?? 15,
        from: meta?.from ?? 0,
        to: meta?.to ?? 0,
    };
}

function normalizeLinks(metaLinks: RawLinks | undefined, fallbackCount: number): PaginatedLinks {
    if (Array.isArray(metaLinks) && metaLinks.length > 0) {
        return metaLinks.map((link) => ({
            url: link.url ?? null,
            label: link.label ?? '',
            active: Boolean(link.active),
        }));
    }

    if (fallbackCount <= 0) {
        return [{ url: null, label: '1', active: true }];
    }

    return Array.from({ length: Math.max(1, fallbackCount) }, (_, i) => ({
        url: null,
        label: String(i + 1),
        active: i === 0,
    }));
}

export async function fetchAdminPaginated<T>(url: string, page: number = 1): Promise<PaginatedResponse<T>> {
    const separator = url.includes('?') ? '&' : '?';
    const response = await fetch(`${url}${separator}page=${encodeURIComponent(page)}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    const payload = (await response.json()) as
        | { data: T[]; links?: unknown; meta?: RawMeta }
        | { data: { data: T[]; links?: unknown; meta?: RawMeta } };

    const nested = (payload as { data: { data: T[]; links?: unknown; meta?: RawMeta } }).data;

    if (Array.isArray((payload as { data: T[] }).data)) {
        const top = payload as { data: T[]; links?: unknown; meta?: RawMeta };
        const meta = normalizeMeta(top.meta);
        const metaLinks = top.meta?.links;
        const links = Array.isArray(metaLinks) && metaLinks.length > 0
            ? normalizeLinks(metaLinks as RawLinks, top.data.length)
            : normalizeLinks(undefined, top.data.length);

        return {
            data: top.data,
            links,
            meta,
        };
    }

    const meta = normalizeMeta(nested.meta);
    const nestedLinks = nested.meta?.links;
    const links = Array.isArray(nestedLinks) && nestedLinks.length > 0
        ? normalizeLinks(nestedLinks as RawLinks, nested.data.length)
        : normalizeLinks(undefined, nested.data.length);

    return {
        data: nested.data,
        links,
        meta,
    };
}
