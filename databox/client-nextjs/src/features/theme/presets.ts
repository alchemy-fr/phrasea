/**
 * Built-in themes offered in the settings menu, independently of the light /
 * dark / system appearance: each one is a light palette with a dark
 * alternative, plus its own font, radius and tracking. The styles live in
 * app/globals.css (`[data-theme='<id>']` and `.dark[data-theme='<id>']`);
 * this module only knows what the menu and the theme manager need.
 */
export type ThemeMode = 'light' | 'dark';

export type ThemePreset = {
    id: string;
    /** i18n key of the label, with its English default */
    label: [key: string, defaultLabel: string];
    /** [background, primary] hex colors of the light palette, for the menu swatch */
    swatch: [background: string, primary: string];
};

/** The base palette of app/globals.css: no `data-theme` on <html> */
export const DEFAULT_THEME_ID = 'default';

/** The organisation theme compiled from the stack configuration */
export const CUSTOM_THEME_ID = 'custom';

/** localStorage key of the selected theme (the appearance is next-themes's `theme`) */
export const THEME_STORAGE_KEY = 'dbx.theme';

export const themePresets: ThemePreset[] = [
    {
        id: 'ocean',
        label: ['theme.preset.ocean', 'Ocean'],
        swatch: ['#f6fbfd', '#0082a9'],
    },
    {
        id: 'forest',
        label: ['theme.preset.forest', 'Forest'],
        swatch: ['#f8fbf8', '#0a7e3a'],
    },
    {
        id: 'sunset',
        label: ['theme.preset.sunset', 'Sunset'],
        swatch: ['#fef9f5', '#d55c13'],
    },
    {
        id: 'lavender',
        label: ['theme.preset.lavender', 'Lavender'],
        swatch: ['#faf9fd', '#8451c9'],
    },
    {
        id: 'sand',
        label: ['theme.preset.sand', 'Sand'],
        swatch: ['#f9f5eb', '#7f4d20'],
    },
    {
        id: 'slate',
        label: ['theme.preset.slate', 'Slate'],
        swatch: ['#f8fafd', '#006d91'],
    },
    {
        id: 'mono',
        label: ['theme.preset.mono', 'Mono'],
        swatch: ['#fafafa', '#2e2e2e'],
    },
    {
        id: 'ember',
        label: ['theme.preset.ember', 'Ember'],
        swatch: ['#fef9f7', '#d25200'],
    },
    {
        id: 'mint',
        label: ['theme.preset.mint', 'Mint'],
        swatch: ['#f7fbf9', '#008760'],
    },
    {
        id: 'plum',
        label: ['theme.preset.plum', 'Plum'],
        swatch: ['#fcf9fc', '#aa4298'],
    },
];

export function findPreset(id: string | undefined): ThemePreset | undefined {
    return themePresets.find(p => p.id === id);
}

/** Whether a stored theme id is one this build knows (the custom one needs the config) */
export function isKnownThemeId(id: string, hasCustom: boolean): boolean {
    return (
        id === DEFAULT_THEME_ID ||
        (id === CUSTOM_THEME_ID && hasCustom) ||
        !!findPreset(id)
    );
}
