import { router } from '@inertiajs/svelte';

export type AdsPerPage = '10' | '20' | '50' | '100' | 'all';

type AdPreferences = {
    perPage: AdsPerPage;
    statusFilter: string | null;
    platform: string | null;
};

const storageKey = 'kleinanz.ads.preferences';

const defaultPreferences: AdPreferences = {
    perPage: '10',
    statusFilter: null,
    platform: null,
};

function readPreferences(): AdPreferences {
    if (typeof localStorage === 'undefined') {
        return defaultPreferences;
    }

    try {
        const storedPreferences = JSON.parse(localStorage.getItem(storageKey) ?? 'null') as Partial<AdPreferences> | null;

        return {
            perPage: ['10', '20', '50', '100', 'all'].includes(storedPreferences?.perPage ?? '')
                ? storedPreferences?.perPage as AdsPerPage
                : defaultPreferences.perPage,
            statusFilter: typeof storedPreferences?.statusFilter === 'string' ? storedPreferences.statusFilter : null,
            platform: typeof storedPreferences?.platform === 'string' ? storedPreferences.platform : null,
        };
    } catch {
        return defaultPreferences;
    }
}

export function adsIndexHref(): string {
    const preferences = readPreferences();
    const query = new URLSearchParams({ per_page: preferences.perPage });

    if (preferences.statusFilter) {
        query.set('status', preferences.statusFilter);
    }

    return `/ads?${query.toString()}`;
}

export function visitSavedAdsIndex(event: MouseEvent): void {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    router.get(adsIndexHref());
}

export function useAdPreferences() {
    let preferences = $state<AdPreferences>(readPreferences());

    function reload(): AdPreferences {
        preferences = readPreferences();

        return preferences;
    }

    function updatePreferences(updates: Partial<AdPreferences>): void {
        preferences = { ...preferences, ...updates };

        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(storageKey, JSON.stringify(preferences));
        }
    }

    return {
        get preferences(): AdPreferences {
            return preferences;
        },
        reload,
        updatePreferences,
    };
}
