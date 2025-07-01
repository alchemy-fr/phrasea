import {create} from 'zustand';
import {ThemeName} from '../lib/theme.ts';
import {Layout} from '../components/AssetList/Layouts';
import {getUserPreferences, putUserPreferences} from '../api/user.ts';
import {DisplayPreferences} from '../components/Media/DisplayContext.tsx';
import {deepEquals} from '@alchemy/core';

export type UserPreferences = {
    theme?: ThemeName | undefined;
    layout?: Layout;
    attrList?: string | undefined;
    display?: DisplayPreferences | undefined;
};

export type UpdatePreferenceHandlerArg<T extends keyof UserPreferences> =
    | ((prev: UserPreferences[T]) => UserPreferences[T])
    | UserPreferences[T]
    | undefined;

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

type UserPreferencesStore = {
    preferences: UserPreferences;
    loading: boolean;
    loaded: boolean;
    load: () => Promise<UserPreferences>;
    updatePreference: <T extends keyof UserPreferences>(
        name: T,
        handler: UpdatePreferenceHandlerArg<T>
    ) => Promise<void>;
};

export const useUserPreferencesStore = create<UserPreferencesStore>(
    (set, get) => ({
        preferences: getFromStorage(),
        loading: false,
        loaded: false,
        load: async () => {
            if (get().loaded) {
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
                });

                return userPreferences;
            } finally {
                set({loading: false});
            }
        },
        updatePreference: async (name, handler) => {
            const prev = get().preferences;
            const newPrefs = {...prev};
            if (typeof handler === 'function') {
                newPrefs[name] = handler(newPrefs[name]);
            } else {
                newPrefs[name] = handler;
            }

            if (deepEquals(newPrefs, prev)) {
                return;
            }

            set({preferences: newPrefs});
            putToStorage(newPrefs);
            setTimeout(() => {
                putUserPreferences(name, newPrefs[name]);
            }, 0);
        },
    })
);
