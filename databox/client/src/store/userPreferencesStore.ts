import {create} from 'zustand';
import {ThemeName} from "../lib/theme.ts";
import {Layout} from "../components/AssetList/Layouts";
import {getUserPreferences, putUserPreferences} from "../api/user.ts";

export type UserPreferences = {
    theme?: ThemeName | undefined;
    pinnedAttrs?: Record<string, string[]> | undefined;
    layout?: Layout;
    attrList?: string | undefined;
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
    isLoading: boolean;
    load: () => Promise<void>;
    updatePreference: <T extends keyof UserPreferences>(
        name: T,
        handler: UpdatePreferenceHandlerArg<T>
    ) => Promise<void>;
};

export const useUserPreferencesStore = create<UserPreferencesStore>((set, get) => ({
    preferences: getFromStorage(),
    isLoading: false,
    load: async () => {
        set({isLoading: true});
        try {
            const userPreferences = await getUserPreferences();
            putToStorage(userPreferences);
            set({preferences: userPreferences, isLoading: false});
        } finally {
            set({isLoading: false});
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
        set({preferences: newPrefs});
        putToStorage(newPrefs);
        setTimeout(() => {
            putUserPreferences(name, newPrefs[name]);
        }, 0);
    },
}));
