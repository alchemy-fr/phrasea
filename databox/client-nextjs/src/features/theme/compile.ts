import {
    ClientThemeMeta,
    compileClientThemeCss,
    normalizeClientTheme,
    themeMeta,
} from './customTheme';

export type CompiledClientTheme = {
    css: string;
    meta: ClientThemeMeta;
};

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

    return {css: compileClientThemeCss(theme), meta: themeMeta(theme)};
}
