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
        | { data: T[]; links?: PaginatedLinks; meta?: PaginatedMeta }
        | { data: { data: T[]; links?: PaginatedLinks; meta?: PaginatedMeta } };

    const nested = (payload as { data: { data: T[]; links?: PaginatedLinks; meta?: PaginatedMeta } }).data;

    if (Array.isArray((payload as { data: T[] }).data)) {
        const top = payload as { data: T[]; links?: PaginatedLinks; meta?: PaginatedMeta };

        return {
            data: top.data,
            links: top.links ?? [{ url: null, label: '1', active: true }],
            meta: top.meta ?? { current_page: 1, last_page: 1, total: top.data.length, per_page: top.data.length, from: 1, to: top.data.length },
        };
    }

    return {
        data: nested.data,
        links: nested.links ?? [{ url: null, label: '1', active: true }],
        meta: nested.meta ?? { current_page: 1, last_page: 1, total: nested.data.length, per_page: nested.data.length, from: 1, to: nested.data.length },
    };
}
