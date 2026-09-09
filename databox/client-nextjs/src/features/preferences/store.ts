import {useMemo} from 'react';
import {create} from 'zustand';
import {api} from '@/lib/api/http';
import {deepEquals} from '@/lib/utils/misc';

export type LayoutMode = 'grid' | 'list';

export type PreviewOptions = {
    sizeRatio: number;
    attributesRatio: number;
    displayAttributes: boolean;
    displayFile: boolean;
};

export type DisplayPreferences = {
    layout: LayoutMode;
    thumbSize: number;
    displayPreview: boolean;
    playVideos: boolean;
    previewLocked: boolean;
    previewOptions: PreviewOptions;
};

export type FacetPreference = {
    name: string;
    hidden?: true;
    order?: number;
};

export type UserPreferences = {
    autoSync?: boolean;
    theme?: string;
    layout?: LayoutMode;
    dataLocale?: string;
    profile?: string | null;
    display?: DisplayPreferences;
    displayBatchEdit?: DisplayPreferences;
    facets?: FacetPreference[];
};

export const defaultDisplayPreferences: DisplayPreferences = {
    layout: 'grid',
    thumbSize: 200,
    displayPreview: true,
    playVideos: false,
    previewLocked: false,
    previewOptions: {
        sizeRatio: 0.5,
        attributesRatio: 0.4,
        displayAttributes: true,
        displayFile: true,
    },
};

const storageKey = 'dbx.prefs';

function readLocal(): UserPreferences {
    try {
        const raw = sessionStorage.getItem(storageKey);

        return raw ? (JSON.parse(raw) as UserPreferences) : {};
    } catch {
        return {};
    }
}

function writeLocal(prefs: UserPreferences): void {
    try {
        sessionStorage.setItem(storageKey, JSON.stringify(prefs));
    } catch {
        // ignore
    }
}

export type UpdatePreference = <K extends keyof UserPreferences>(
    name: K,
    value:
        | UserPreferences[K]
        | ((prev: UserPreferences[K]) => UserPreferences[K]),
    options?: {
        reset?: boolean;
        offlineUpdates?: UserPreferences;
        persist?: boolean;
    }
) => Promise<void>;

type State = {
    preferences: UserPreferences;
    loaded: boolean;
    authenticated: boolean;
    load: (authenticated: boolean) => Promise<void>;
    updatePreference: UpdatePreference;
    /** Called by the profile store when a preference change must be synced to the current profile instead */
    onBeforePersist?: (
        name: keyof UserPreferences,
        prefs: UserPreferences
    ) => boolean;
};

export const usePreferencesStore = create<State>((set, get) => ({
    preferences: typeof window !== 'undefined' ? readLocal() : {},
    loaded: false,
    authenticated: false,

    load: async authenticated => {
        if (!authenticated) {
            set({loaded: true, authenticated: false});

            return;
        }
        try {
            const prefs = await api.get<UserPreferences>('/preferences');
            writeLocal(prefs ?? {});
            set({preferences: prefs ?? {}, loaded: true, authenticated: true});
        } catch {
            set({loaded: true, authenticated: true});
        }
    },

    updatePreference: async (name, value, options = {}) => {
        const prev = get().preferences;
        const next: UserPreferences = options.reset ? {} : {...prev};
        next[name] =
            typeof value === 'function'
                ? (value as (p: any) => any)(next[name])
                : value;
        if (options.offlineUpdates) {
            Object.assign(next, options.offlineUpdates);
        }
        if (deepEquals(next, prev)) {
            return;
        }
        set({preferences: next});
        writeLocal(next);

        if (deepEquals(next[name], prev[name]) || options.persist === false) {
            return;
        }
        if (get().onBeforePersist?.(name, next) === false) {
            return;
        }
        if (get().authenticated) {
            await api
                .put('/preferences', {
                    name,
                    value: next[name],
                    reset: options.reset,
                })
                .catch(e => console.warn('[preferences] save failed', e));
        }
    },
}));

export function useDisplayPreferences(
    key: 'display' | 'displayBatchEdit' = 'display'
) {
    // Select the raw slice only: the selector must return a stable reference,
    // the merged object is memoized on it.
    const raw = usePreferencesStore(s => s.preferences[key]);

    return useMemo(
        () => ({
            ...defaultDisplayPreferences,
            ...(raw ?? {}),
            previewOptions: {
                ...defaultDisplayPreferences.previewOptions,
                ...(raw?.previewOptions ?? {}),
            },
        }),
        [raw]
    );
}
