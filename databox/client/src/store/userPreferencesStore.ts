import {create} from 'zustand';
import type {ThemeName} from '@alchemy/phrasea-framework';
import {Layout} from '../components/AssetList/Layouts';
import {
    getUserPreferences,
    PutPreferenceOptions,
    putUserPreferences,
} from '../api/user.ts';
import {DisplayPreferences} from '../components/Media/DisplayContext.tsx';
import {deepEquals} from '@alchemy/core';
import {oauthClient} from '../init.ts';
import {FacetPreference} from '../components/Media/Asset/Facets/facetTypes.ts';
import {Profile} from '../types.ts';

export type UserPreferences = {
    theme?: ThemeName | undefined;
    layout?: Layout;
    dataLocale?: string | undefined;
    profile?: string | null | undefined;
    display?: DisplayPreferences | undefined;
    displayBatchEdit?: DisplayPreferences | undefined;
    facets?: FacetPreference[];
};

export type UpdatePreferenceHandlerArg<T extends keyof UserPreferences> =
    UpdatePreferenceHandlerArgT<UserPreferences[T]>;

export type UpdatePreferenceHandlerArgT<T> = ((prev: T) => T) | T | undefined;

const sessionStorageKey = 'userPrefs';

function getFromStorage(): UserPreferences {
    const item = sessionStorage.getItem(sessionStorageKey);
    if (item) {
        return JSON.parse(item);
    }
    return {};
}

function putToStorage(prefs: UserPreferences): void {
    sessionStorage.setItem(sessionStorageKey, JSON.stringify(prefs));
}

export type UpdatePreference = <T extends keyof UserPreferences>(
    name: T,
    handler: UpdatePreferenceHandlerArg<T>,
    options?: PutPreferenceOptions
) => Promise<void>;

type ApplyProfile = (profile: Profile | null) => Promise<void>;

type UserPreferencesStore = {
    preferences: UserPreferences;
    loading: boolean;
    loaded: boolean;
    userLoaded: boolean;
    load: () => Promise<UserPreferences>;
    updatePreference: UpdatePreference;
    applyProfile: ApplyProfile;
};

export const useUserPreferencesStore = create<UserPreferencesStore>(
    (set, get) => ({
        preferences: getFromStorage(),
        loading: false,
        loaded: false,
        userLoaded: false,
        load: async () => {
            if (await oauthClient.isAuthenticated()) {
                if (get().userLoaded) {
                    return get().preferences;
                }

                set({loading: true});
                try {
                    const userPreferences = await getUserPreferences();
                    putToStorage(userPreferences);
                    set({
                        preferences: userPreferences,
                        loading: false,
                        loaded: true,
                        userLoaded: true,
                    });

                    return userPreferences;
                } finally {
                    set({loading: false});
                }
            } else {
                set({loaded: true});

                return get().preferences;
            }
        },
        updatePreference: async (name, handler, options) => {
            const prev = get().preferences;
            const newPrefs = options?.reset ? {} : {...prev};
            if (typeof handler === 'function') {
                newPrefs[name] = handler(newPrefs[name]);
            } else {
                newPrefs[name] = handler;
            }

            if (deepEquals(newPrefs, prev)) {
                return;
            }

            if (options?.offlineUpdates) {
                Object.entries(options.offlineUpdates).map(([k, v]) => {
                    // @ts-expect-error Unknown
                    newPrefs[k] = v;
                });
            }

            set({preferences: newPrefs});
            putToStorage(newPrefs);

            return new Promise(resolve => {
                setTimeout(async () => {
                    if (await oauthClient.isAuthenticated()) {
                        putUserPreferences(name, newPrefs[name], options).then(
                            () => {
                                resolve();
                            }
                        );
                    } else {
                        resolve();
                    }
                }, 0);
            });
        },
        applyProfile: async profile => {
            const state = get();
            if (profile) {
                return state.updatePreference('profile', profile.id, {
                    reset: true,
                    offlineUpdates: profile.data,
                });
            } else {
                return state.updatePreference('profile', null);
            }
        },
    })
);
