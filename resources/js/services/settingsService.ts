export type PublicSettings = Record<string, unknown>;

let cache: PublicSettings | null = null;
let inflight: Promise<PublicSettings> | null = null;

export async function fetchPublicSettings(
    force = false,
): Promise<PublicSettings> {
    if (cache !== null && !force) {
        return cache;
    }

    if (inflight !== null && !force) {
        return inflight;
    }

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return {};
    }

    inflight = (async () => {
        const response = await fetch('/api/v1/settings/public', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Failed to load public settings.');
        }

        const values = (await response.json()) as PublicSettings;

        for (const key of [
            'branding.logo_url',
            'branding.favicon_url',
            'payment.qr_code_url',
            ...Array.from({ length: 10 }, (_, index) => `homepage.banner_${index + 1}_url`),
            'homepage.right_top_banner_url',
            'homepage.right_bottom_banner_url',
        ]) {
            const value = values[key];
            if (typeof value === 'string' && value !== '') {
                try {
                    const url = new URL(value, window.location.origin);
                    values[key] = url.pathname.startsWith('/storage/')
                        ? `${url.pathname}${url.search}`
                        : value;
                } catch {
                    values[key] = `/storage/${value.replace(/^\/+/, '')}`;
                }
            }
        }

        cache = values;

        return cache;
    })();

    try {
        return await inflight;
    } finally {
        inflight = null;
    }
}

export function getSetting<T>(key: string, fallback: T): T {
    if (cache === null || !(key in cache)) {
        return fallback;
    }

    return cache[key] as T;
}

export function clearSettingsCache(): void {
    cache = null;
}
