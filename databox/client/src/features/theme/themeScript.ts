import type {ClientThemeMeta} from './customTheme';
import {CUSTOM_THEME_ID, DEFAULT_THEME_ID, THEME_STORAGE_KEY} from './presets';

export type PrepaintOptions = {
    storageKey: string;
    defaultTheme: string;
    knownThemes: string[];
};

/**
 * Runs before the first paint, next to the next-themes script (which handles
 * the light / dark appearance): sets `data-theme` on <html> from the stored
 * theme choice, so that a reload does not flash the base palette. Written as
 * a plain function so that it can be unit tested and serialized with
 * `toString()`: no closure, ES5 only.
 */
/* eslint-disable no-var */
export function prepaintTheme(win: Window, o: PrepaintOptions): void {
    try {
        var theme = win.localStorage.getItem(o.storageKey) || o.defaultTheme;
        if (theme !== 'default' && o.knownThemes.indexOf(theme) !== -1) {
            win.document.documentElement.setAttribute('data-theme', theme);
        }
    } catch {
        // no storage: the base palette it is
    }
}
/* eslint-enable no-var */

/** The options for a page whose organisation theme is `meta` (if any) */
export function prepaintOptionsFor(
    meta: ClientThemeMeta | undefined,
    presetIds: string[]
): PrepaintOptions {
    return {
        storageKey: THEME_STORAGE_KEY,
        defaultTheme: meta?.default ? CUSTOM_THEME_ID : DEFAULT_THEME_ID,
        knownThemes: meta ? [...presetIds, CUSTOM_THEME_ID] : presetIds,
    };
}

export function buildPrepaintScript(options: PrepaintOptions): string {
    return `(${prepaintTheme.toString()})(window,${JSON.stringify(options)})`;
}
