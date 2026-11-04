import {
    ClientThemeMeta,
    compileClientThemeCss,
    normalizeClientTheme,
    themeFontFacesCss,
    themeMeta,
} from './customTheme';

export type CompiledClientTheme = {
    /** Palette and style: inlined in the page, it must never flash */
    css: string;
    /**
     * `@font-face` rules of the uploaded fonts, served apart (they are large
     * and identical from one page to the next), addressed by `fontsHash`.
     * Empty when the theme uses no uploaded font.
     */
    fontsCss: string;
    fontsHash: string;
    meta: ClientThemeMeta;
};

/** Short, stable digest of a string — enough to address a stylesheet */
export function hashCss(css: string): string {
    let hash = 0x811c9dc5;
    for (let i = 0; i < css.length; i++) {
        hash ^= css.charCodeAt(i);
        hash = Math.imul(hash, 0x01000193);
    }

    return (hash >>> 0).toString(36);
}

/**
 * Compiles the organisation theme found in the stack configuration
 * (`databox.theme`, stored as a JSON string by the configurator, or already
 * decoded) into the stylesheet served with the page and the metadata the
 * client needs. Anything that is not a valid theme is ignored.
 */
export function compileStackTheme(
    stackConfig: unknown
): CompiledClientTheme | null {
    const databox = (stackConfig as {databox?: unknown} | null)?.databox;
    let raw = (databox as {theme?: unknown} | null)?.theme;
    if (typeof raw === 'string') {
        try {
            raw = JSON.parse(raw);
        } catch {
            return null;
        }
    }
    const theme = normalizeClientTheme(raw);
    if (!theme) {
        return null;
    }
    const fontsCss = themeFontFacesCss(theme);

    return {
        css: compileClientThemeCss(theme),
        fontsCss,
        fontsHash: hashCss(fontsCss),
        meta: themeMeta(theme),
    };
}
