import {create} from 'zustand';
import type {
    BaseAttributeDefinition,
    DisplayProfile,
    ProfileItem,
} from '@/types/api';
import {ProfileItemSection, ProfileItemType} from '@/types/api';
import {
    addToProfile,
    deleteProfile,
    getProfile,
    getProfiles,
    putProfile,
    putProfileItem,
    removeFromProfile,
    sortProfileItems,
} from '@/lib/api/misc';
import {
    usePreferencesStore,
    UserPreferences,
} from '@/features/preferences/store';
import {deepEquals} from '@/lib/utils/misc';

type State = {
    profiles: DisplayProfile[];
    current: DisplayProfile | undefined;
    loaded: boolean;
    loading: boolean;
    next?: string;
    load: (force?: boolean) => Promise<void>;
    loadMore: () => Promise<void>;
    setCurrent: (profile: DisplayProfile | undefined) => Promise<void>;
    upsert: (profile: DisplayProfile) => void;
    remove: (id: string) => Promise<void>;
    toggleDefinition: (definition: BaseAttributeDefinition) => Promise<void>;
    addItems: (items: ProfileItem[]) => Promise<void>;
    removeItems: (ids: string[]) => Promise<void>;
    updateItem: (itemId: string, data: Partial<ProfileItem>) => Promise<void>;
    sortItems: (ids: string[]) => Promise<void>;
    syncPreferences: () => Promise<void>;
    isSynced: () => boolean;
};

const syncedKeys: (keyof UserPreferences)[] = [
    'layout',
    'display',
    'displayBatchEdit',
    'facets',
    'theme',
];

function extractSyncedPrefs(prefs: UserPreferences): Record<string, unknown> {
    const out: Record<string, unknown> = {};
    syncedKeys.forEach(k => {
        if (prefs[k] !== undefined) {
            out[k] = prefs[k];
        }
    });

    return out;
}

export const useProfileStore = create<State>((set, get) => ({
    profiles: [],
    current: undefined,
    loaded: false,
    loading: false,

    load: async force => {
        if ((get().loaded || get().loading) && !force) {
            return;
        }
        set({loading: true});
        try {
            const prefProfile =
                usePreferencesStore.getState().preferences.profile;
            const [page, current] = await Promise.all([
                getProfiles(),
                prefProfile
                    ? getProfile(prefProfile).catch(() => undefined)
                    : Promise.resolve(undefined),
            ]);
            set({
                profiles: page.items,
                next: page.next,
                current: current ?? get().current,
                loaded: true,
            });
        } finally {
            set({loading: false});
        }
    },

    loadMore: async () => {
        const {next} = get();
        if (!next) {
            return;
        }
        const page = await getProfiles({url: next});
        set(s => ({profiles: [...s.profiles, ...page.items], next: page.next}));
    },

    setCurrent: async profile => {
        set({current: profile});
        const prefs = usePreferencesStore.getState();
        if (profile) {
            await prefs.updatePreference('profile', profile.id, {
                reset: true,
                offlineUpdates: (profile.data ?? {}) as UserPreferences,
            });
        } else {
            await prefs.updatePreference('profile', null, {reset: true});
        }
    },

    upsert: profile =>
        set(s => ({
            profiles: s.profiles.some(p => p.id === profile.id)
                ? s.profiles.map(p => (p.id === profile.id ? profile : p))
                : [profile, ...s.profiles],
            current: s.current?.id === profile.id ? profile : s.current,
        })),

    remove: async id => {
        await deleteProfile(id);
        set(s => ({profiles: s.profiles.filter(p => p.id !== id)}));
        if (get().current?.id === id) {
            await get().setCurrent(undefined);
        }
    },

    toggleDefinition: async definition => {
        const {current} = get();
        const isBuiltIn = !!definition.builtIn;
        const existing = current?.items?.find(
            i =>
                i.section === ProfileItemSection.Attributes &&
                (isBuiltIn
                    ? i.key === definition.searchSlug
                    : i.definition === definition.id)
        );
        if (existing && current) {
            await get().removeItems([existing.id]);
        } else {
            await get().addItems([
                {
                    id: '',
                    section: ProfileItemSection.Attributes,
                    type: isBuiltIn
                        ? ProfileItemType.BuiltIn
                        : ProfileItemType.Definition,
                    key: isBuiltIn ? definition.searchSlug : undefined,
                    definition: isBuiltIn ? undefined : definition.id,
                },
            ]);
        }
    },

    addItems: async items => {
        const {current} = get();
        const profile = await addToProfile(current?.id, {
            items,
        } as any as ProfileItem[]);
        get().upsert(profile);
        if (!current) {
            await get().setCurrent(profile);
        }
    },

    removeItems: async ids => {
        const {current} = get();
        if (!current) {
            return;
        }
        const profile = await removeFromProfile(current.id, ids);
        get().upsert(profile);
    },

    updateItem: async (itemId, data) => {
        const {current} = get();
        if (!current) {
            return;
        }
        const item = await putProfileItem(current.id, itemId, data);
        get().upsert({
            ...current,
            items: (current.items ?? []).map(i =>
                i.id === itemId ? {...i, ...item} : i
            ),
        });
    },

    sortItems: async ids => {
        const {current} = get();
        if (!current) {
            return;
        }
        await sortProfileItems(current.id, ids);
        const index = new Map(ids.map((id, i) => [id, i]));
        get().upsert({
            ...current,
            items: [...(current.items ?? [])].sort(
                (a, b) => (index.get(a.id) ?? 9999) - (index.get(b.id) ?? 9999)
            ),
        });
    },

    syncPreferences: async () => {
        const {current} = get();
        if (!current?.capabilities.edit) {
            return;
        }
        const data = extractSyncedPrefs(
            usePreferencesStore.getState().preferences
        );
        const updated = await putProfile(current.id, {data});
        get().upsert(updated);
    },

    isSynced: () => {
        const {current} = get();
        if (!current) {
            return true;
        }
        const prefs = extractSyncedPrefs(
            usePreferencesStore.getState().preferences
        );

        return deepEquals(
            prefs,
            extractSyncedPrefs((current.data ?? {}) as UserPreferences)
        );
    },
}));

// Auto-sync preference changes to the current profile when enabled.
usePreferencesStore.setState({
    onBeforePersist: (name, prefs) => {
        if (name === 'profile' || !prefs.profile || !prefs.autoSync) {
            return true;
        }
        const store = useProfileStore.getState();
        if (
            store.current?.capabilities.edit &&
            (syncedKeys as string[]).includes(name)
        ) {
            void store.syncPreferences();

            return false;
        }

        return true;
    },
});
