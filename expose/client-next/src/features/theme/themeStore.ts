import {create} from 'zustand';
import {DEFAULT_THEME_ID, THEME_STORAGE_KEY} from './presets';

function readStored(): string | undefined {
    try {
        return localStorage.getItem(THEME_STORAGE_KEY) ?? undefined;
    } catch {
        return undefined;
    }
}

type State = {
    /** Selected theme id (preset, `custom` or `default`); undefined until known on the client */
    theme: string | undefined;
    /** Selects a theme, persisted in localStorage */
    setTheme: (theme: string) => void;
};

/**
 * The selected theme (palette + style). The light / dark / system appearance
 * is a separate, general choice handled by next-themes.
 */
export const useThemeStore = create<State>(set => ({
    theme: typeof window !== 'undefined' ? readStored() : undefined,

    setTheme: theme => {
        set({theme});
        try {
            if (theme === DEFAULT_THEME_ID) {
                localStorage.removeItem(THEME_STORAGE_KEY);
            } else {
                localStorage.setItem(THEME_STORAGE_KEY, theme);
            }
        } catch {
            // storage unavailable
        }
    },
}));
