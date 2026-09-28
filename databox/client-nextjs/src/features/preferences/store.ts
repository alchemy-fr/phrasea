import {useMemo} from 'react';
import {create} from 'zustand';
import {api} from '@/lib/api/http';
import {deepEquals} from '@/lib/utils/misc';

export type LayoutMode = 'grid' | 'list';

/** How thumbnails fill their box: `contain` keeps the whole image, `cover` crops it */
export type ThumbFit = 'contain' | 'cover';

export type PreviewOptions = {
    sizeRatio: number;
    attributesRatio: number;
    displayAttributes: boolean;
    displayFile: boolean;
};

export type DisplayPreferences = {
    layout: LayoutMode;
    thumbSize: number;
    thumbFit: ThumbFit;
    displayPreview: boolean;
    /** Auto play the media (video, sound) shown in the hover preview */
    playVideos: boolean;
    /** Play a video/sound thumbnail while the mouse is over it */
    playOnHover: boolean;
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
    /** Appearance: light, dark or system */
    theme?: string;
    /** Selected theme (palette + style): a preset id, `custom` or `default` */
    palette?: string;
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
    thumbFit: 'contain',
    displayPreview: true,
    playVideos: false,
    playOnHover: false,
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
        /**
         * Saves at most once per this many milliseconds (the last value
         * always ends up saved): for continuous inputs such as sliders. The
         * local state is updated right away.
         */
        throttle?: number;
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

/** Preferences in a throttle window: whether a save is due at its end */
const throttled = new Map<keyof UserPreferences, {pending: boolean}>();

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
        if (!options.throttle) {
            return persist(name, options.reset);
        }
        const current = throttled.get(name);
        if (current) {
            current.pending = true;

            return;
        }
        const delay = options.throttle;
        const open = () => {
            const w = {pending: false};
            throttled.set(name, w);
            setTimeout(() => {
                throttled.delete(name);
                if (w.pending) {
                    open();
                    void persist(name);
                }
            }, delay);
        };
        open();

        return persist(name, options.reset);
    },
}));

/** Saves in flight, by preference name */
const saving = new Map<keyof UserPreferences, Promise<void>>();
/** Saves requested while one was in flight: `reset` of the queued save */
const queued = new Map<keyof UserPreferences, boolean>();

/**
 * Saves the current value of a preference. The saves of a preference are
 * sent one at a time: concurrent requests could be applied in any order and
 * leave an older value on the server (quick successive toggles). A save
 * requested meanwhile sends the latest value once the current one is done,
 * or right away when the page is left (see `flushQueuedSaves`).
 */
function persist(name: keyof UserPreferences, reset?: boolean): Promise<void> {
    const current = saving.get(name);
    if (current) {
        queued.set(name, (queued.get(name) ?? false) || !!reset);

        return current;
    }

    const run = (async () => {
        let nextReset: boolean | undefined = reset;
        for (;;) {
            await save(name, nextReset);
            if (!queued.has(name)) {
                return;
            }
            nextReset = queued.get(name) || undefined;
            queued.delete(name);
        }
    })().finally(() => saving.delete(name));
    saving.set(name, run);

    return run;
}

/** A queued save must not be lost when the page is left (reload, close…) */
function flushQueuedSaves() {
    queued.forEach((reset, name) => {
        queued.delete(name);
        void save(name, reset || undefined, true);
    });
}
if (typeof window !== 'undefined') {
    window.addEventListener('pagehide', flushQueuedSaves);
}

async function save(
    name: keyof UserPreferences,
    reset?: boolean,
    keepalive?: boolean
): Promise<void> {
    const {preferences, onBeforePersist, authenticated} =
        usePreferencesStore.getState();
    if (onBeforePersist?.(name, preferences) === false) {
        return;
    }
    if (authenticated) {
        await api
            .put(
                '/preferences',
                {name, value: preferences[name], reset},
                keepalive ? {keepalive} : undefined
            )
            .catch(e => console.warn('[preferences] save failed', e));
    }
}

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
