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
    getUserDefaultProfile,
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
import {deepEquals} from '@alchemy/ui/utils/misc';

type State = {
    profiles: DisplayProfile[];
    /** The profile applied: the chosen one, else the user default */
    current: DisplayProfile | undefined;
    /**
     * The default profile of the user, among the ones they read, applied
     * while none is chosen
     */
    userDefault?: DisplayProfile;
    /** Whether `current` is the user default, none being chosen */
    currentIsDefault: boolean;
    loaded: boolean;
    loading: boolean;
    next?: string;
    load: (force?: boolean) => Promise<void>;
    loadMore: () => Promise<void>;
    setCurrent: (profile: DisplayProfile | undefined) => Promise<void>;
    upsert: (profile: DisplayProfile) => void;
    remove: (id: string) => Promise<void>;
    /** Resolves the user default again (e.g. a default flag changed) */
    reloadUserDefault: () => Promise<void>;
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
    'sidebarSections',
    'assetPanelTabs',
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

/**
 * Applies the synced preferences of the user default to the ones the
 * user has not set, without saving them: a choice of the user wins. The
 * values of a previous default are removed first.
 */
function applyDefaultPreferences(profile: DisplayProfile | undefined): void {
    usePreferencesStore
        .getState()
        .applyFallback(
            profile
                ? extractSyncedPrefs((profile.data ?? {}) as UserPreferences)
                : {}
        );
}

/** A profile the user may not edit is not changed from the profile tools */
function isReadOnly(profile: DisplayProfile | undefined): boolean {
    return !!profile && !profile.capabilities?.edit;
}

export const useProfileStore = create<State>((set, get) => ({
    profiles: [],
    current: undefined,
    currentIsDefault: false,
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
            const [page, chosen, userDefault] = await Promise.all([
                getProfiles(),
                prefProfile
                    ? getProfile(prefProfile).catch(() => undefined)
                    : Promise.resolve(undefined),
                // Also named by the "default" entry when a profile is chosen
                getUserDefaultProfile().catch(() => undefined),
            ]);
            // A chosen profile no longer readable falls back to the default
            const fallback = !chosen && !!userDefault;
            set({
                profiles: page.items,
                next: page.next,
                userDefault,
                current: fallback
                    ? userDefault
                    : (chosen ??
                      (get().currentIsDefault ? undefined : get().current)),
                currentIsDefault: fallback,
                loaded: true,
            });
            applyDefaultPreferences(fallback ? userDefault : undefined);
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

    setCurrent: async listed => {
        // A profile of the list has neither its items nor its data
        const profile =
            listed && !listed.items ? await getProfile(listed.id) : listed;
        const {userDefault} = get();
        set({
            current: profile ?? userDefault,
            currentIsDefault: !profile && !!userDefault,
        });
        const prefs = usePreferencesStore.getState();
        if (profile) {
            await prefs.updatePreference('profile', profile.id, {
                reset: true,
                offlineUpdates: (profile.data ?? {}) as UserPreferences,
            });
        } else {
            // The preferences are reset (synchronously, before the save
            // resolves): the default ones all apply
            const saved = prefs.updatePreference('profile', null, {
                reset: true,
            });
            applyDefaultPreferences(userDefault);
            await saved;
        }
    },

    upsert: profile =>
        set(s => ({
            profiles: s.profiles.some(p => p.id === profile.id)
                ? s.profiles.map(p => (p.id === profile.id ? profile : p))
                : [profile, ...s.profiles],
            current: s.current?.id === profile.id ? profile : s.current,
            userDefault:
                s.userDefault?.id === profile.id ? profile : s.userDefault,
        })),

    remove: async id => {
        await deleteProfile(id);
        const {current, currentIsDefault, userDefault} = get();
        set(s => ({profiles: s.profiles.filter(p => p.id !== id)}));
        if (userDefault?.id === id) {
            // Another default may apply now
            await get().reloadUserDefault();
        }
        // Without chosen profile, the preferences of the user are kept:
        // reloading the default has applied the next one
        if (current?.id === id && !currentIsDefault) {
            await get().setCurrent(undefined);
        }
    },

    reloadUserDefault: async () => {
        const userDefault = await getUserDefaultProfile().catch(
            () => undefined
        );
        const {current, currentIsDefault, loaded} = get();
        // The default applies while no profile is chosen
        const applies = currentIsDefault || (loaded && !current);
        set({
            userDefault,
            ...(applies
                ? {current: userDefault, currentIsDefault: !!userDefault}
                : {}),
        });
        if (applies) {
            applyDefaultPreferences(userDefault);
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
        if (isReadOnly(current)) {
            return;
        }
        const profile = await addToProfile(current?.id, items);
        get().upsert(profile);
        if (!current) {
            await get().setCurrent(profile);
        }
    },

    removeItems: async ids => {
        const {current} = get();
        if (!current || isReadOnly(current)) {
            return;
        }
        const profile = await removeFromProfile(current.id, ids);
        get().upsert(profile);
    },

    updateItem: async (itemId, data) => {
        const {current} = get();
        if (!current || isReadOnly(current)) {
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
        if (!current || isReadOnly(current)) {
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
        const {current, currentIsDefault} = get();
        // The preferences of a user without chosen profile are their own
        if (!current?.capabilities.edit || currentIsDefault) {
            return;
        }
        const data = extractSyncedPrefs(
            usePreferencesStore.getState().preferences
        );
        const updated = await putProfile(current.id, {data});
        get().upsert(updated);
    },

    isSynced: () => {
        const {current, currentIsDefault} = get();
        if (!current || currentIsDefault) {
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
