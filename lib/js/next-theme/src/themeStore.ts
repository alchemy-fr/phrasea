import {create} from 'zustand';
import type {ClientTheme} from './customTheme';
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
    /** Draft applied to the whole page while a theme editor is open */
    preview: ClientTheme | null;
    /** Selects a theme, remembered in localStorage */
    setTheme: (theme: string) => void;
    setPreview: (preview: ClientTheme | null) => void;
};

/**
 * The selected theme (palette + style). The light / dark / system appearance
 * is a separate, general choice handled by next-themes.
 */
export const useThemeStore = create<State>(set => ({
    theme: typeof window !== 'undefined' ? readStored() : undefined,
    preview: null,

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

    setPreview: preview => set({preview}),
}));
