import {CUSTOM_THEME_ID, type ThemeMode} from './presets';
import {findThemeFont, themeFontStack} from './fonts';

/**
 * The organisation theme: a light palette, an optional dark alternative and
 * a few style properties defined by an administrator (see
 * ThemeEditorScreen), stored as the `databox.theme` entry of the stack
 * configuration and compiled on the server into the CSS of the page (see
 * compile.ts): selecting it sets `data-theme="custom"` on <html>, the
 * appearance (light / dark / system) stays the user's general choice. While
 * it is being edited, the draft is applied as inline custom properties.
 */

export const THEME_COLOR_TOKENS = [
    'background',
    'foreground',
    'card',
    'card-foreground',
    'popover',
    'popover-foreground',
    'primary',
    'primary-foreground',
    'secondary',
    'secondary-foreground',
    'muted',
    'muted-foreground',
    'accent',
    'accent-foreground',
    'destructive',
    'destructive-foreground',
    'warning',
    'warning-foreground',
    'success',
    'success-foreground',
    'border',
    'input',
    'ring',
    'sidebar',
    'sidebar-foreground',
    'media-bg',
] as const;

export type ThemeColorToken = (typeof THEME_COLOR_TOKENS)[number];

export type ThemeColors = Partial<Record<ThemeColorToken, string>>;

/**
 * A font uploaded by an administrator, stored in the configurator entry as a
 * data URI — the entry is a text value, like the logo of the stack
 * configuration — and served to the browser as an `@font-face` rule.
 */
export type ClientThemeFont = {
    /** The family name the theme refers to */
    family: string;
    /** `data:font/woff2;base64,…` */
    src: string;
    /** `normal`, `bold` or a number; several faces make a family */
    weight?: string;
    style?: 'normal' | 'italic';
};

export type ClientTheme = {
    name: string;
    /** Applied to users who never picked a theme themselves */
    default: boolean;
    /** Light palette, hex colors (#rrggbb); missing tokens fall back to the base light palette */
    colors: ThemeColors;
    /** Dark alternative; when absent the base dark palette is used with the theme's style */
    dark?: ThemeColors;
    /** Corner radius in rem */
    radius?: number;
    /** Base font size in px */
    fontSize?: number;
    /**
     * A font of `fonts`, the id of a built-in Google font (see fonts.ts), or
     * a plain CSS font list for fonts installed on the users' devices.
     */
    fontFamily?: string;
    /** Body letter spacing in em */
    letterSpacing?: number;
    /** Fonts uploaded by the administrator, served as `@font-face` rules */
    fonts?: ClientThemeFont[];
};

export const THEME_LIMITS = {
    radius: {min: 0, max: 2, step: 0.125, default: 0.5},
    fontSize: {min: 11, max: 20, default: 14},
    letterSpacing: {min: -0.05, max: 0.1, step: 0.005, default: 0},
    nameMaxLength: 50,
    fontFamilyMaxLength: 120,
    fonts: {
        max: 4,
        familyMaxLength: 50,
        /**
         * Characters of the data URI, so roughly 3/4 of that in bytes: a
         * subsetted woff2 is an order of magnitude smaller. They go through
         * the configuration of the stack, which every page of every client
         * reads.
         */
        srcMaxLength: 300_000,
        types: ['woff2', 'woff', 'ttf', 'otf'] as const,
        weights: [
            '100',
            '200',
            '300',
            'normal',
            '500',
            '600',
            'bold',
            '800',
            '900',
        ] as const,
        styles: ['normal', 'italic'] as const,
    },
} as const;

export const FONT_FAMILY_NAME_RE = /^[a-zA-Z0-9][a-zA-Z0-9 _-]*$/;
export const FONT_SRC_RE =
    /^data:font\/(woff2|woff|ttf|otf);base64,[A-Za-z0-9+/]+={0,2}$/;

const fontFormats: Record<string, string> = {
    woff2: 'woff2',
    woff: 'woff',
    ttf: 'truetype',
    otf: 'opentype',
};

/**
 * Hex equivalents of the base palettes of app/globals.css (`:root` and
 * `.dark`), used to pre-fill the editor and to complete a partial palette.
 */
export const baseThemeColors: Record<
    ThemeMode,
    Record<ThemeColorToken, string>
> = {
    light: {
        'background': '#f9fafb',
        'foreground': '#11161f',
        'card': '#ffffff',
        'card-foreground': '#11161f',
        'popover': '#ffffff',
        'popover-foreground': '#11161f',
        'primary': '#2867e4',
        'primary-foreground': '#fcfcfc',
        'secondary': '#eaeff5',
        'secondary-foreground': '#1c222b',
        'muted': '#ecf1f5',
        'muted-foreground': '#5d646f',
        'accent': '#e4ecf9',
        'accent-foreground': '#142139',
        'destructive': '#e62b34',
        'destructive-foreground': '#fcfcfc',
        'warning': '#f19700',
        'warning-foreground': '#311d03',
        'success': '#1eab53',
        'success-foreground': '#fcfcfc',
        'border': '#d9dfe5',
        'input': '#d9dfe5',
        'ring': '#447be4',
        'sidebar': '#f3f5f8',
        'sidebar-foreground': '#1c222b',
        'media-bg': '#e1e5ea',
    },
    dark: {
        'background': '#0c1016',
        'foreground': '#eceff2',
        'card': '#14181f',
        'card-foreground': '#eceff2',
        'popover': '#171b22',
        'popover-foreground': '#eceff2',
        'primary': '#669bff',
        'primary-foreground': '#050b18',
        'secondary': '#22272e',
        'secondary-foreground': '#eceff2',
        'muted': '#21242a',
        'muted-foreground': '#989fa8',
        'accent': '#232e42',
        'accent-foreground': '#eceff2',
        'destructive': '#f14d4c',
        'destructive-foreground': '#fcfcfc',
        'warning': '#f7a224',
        'warning-foreground': '#211201',
        'success': '#4cb86a',
        'success-foreground': '#030f05',
        'border': '#2a2e34',
        'input': '#2f3339',
        'ring': '#4c7dd9',
        'sidebar': '#12161d',
        'sidebar-foreground': '#e5e8eb',
        'media-bg': '#040609',
    },
};

export const HEX_COLOR_RE = /^#[0-9a-f]{6}$/i;

export function isHexColor(value: string | undefined): value is string {
    return !!value && HEX_COLOR_RE.test(value);
}

function completeColors(
    mode: ThemeMode,
    colors: ThemeColors | undefined
): Record<ThemeColorToken, string> {
    const result = {...baseThemeColors[mode]};
    for (const token of THEME_COLOR_TOKENS) {
        const value = colors?.[token];
        if (isHexColor(value)) {
            result[token] = value.toLowerCase();
        }
    }

    return result;
}

/**
 * The full palette of a theme for an appearance: its colors on top of the
 * base ones. Without a dark alternative, the dark appearance is the base
 * dark palette.
 */
export function resolveThemeColors(
    theme: Pick<ClientTheme, 'colors' | 'dark'>,
    mode: ThemeMode
): Record<ThemeColorToken, string> {
    return completeColors(mode, mode === 'dark' ? theme.dark : theme.colors);
}

/** The color custom properties of a theme for an appearance */
export function themeColorVars(
    theme: ClientTheme,
    mode: ThemeMode
): Record<string, string> {
    const colors = resolveThemeColors(theme, mode);
    const vars: Record<string, string> = {};
    for (const token of THEME_COLOR_TOKENS) {
        vars[`--${token}`] = colors[token];
    }
    vars['--selection'] =
        `color-mix(in srgb, ${colors.primary} ${mode === 'dark' ? 18 : 12}%, transparent)`;

    return vars;
}

/**
 * The CSS font list of `fontFamily`: an uploaded family is quoted and backed
 * by the generic sans, a built-in Google font goes through the custom
 * property Next defines for it (its real family name is generated), and
 * anything else is taken as the CSS list it is.
 */
export function resolveThemeFontFamily(
    theme: Pick<ClientTheme, 'fontFamily' | 'fonts'>
): string | undefined {
    const family = theme.fontFamily?.trim();
    if (!family) {
        return undefined;
    }
    if (theme.fonts?.some(f => f.family === family)) {
        return `'${family}', ui-sans-serif, system-ui, sans-serif`;
    }
    const builtIn = findThemeFont(family);

    return builtIn ? themeFontStack(builtIn) : family;
}

/** The style custom properties of a theme (same for both appearances) */
export function themeStyleVars(theme: ClientTheme): Record<string, string> {
    const vars: Record<string, string> = {
        '--radius': `${theme.radius ?? THEME_LIMITS.radius.default}rem`,
    };
    if (theme.fontSize) {
        vars['--font-size'] = `${theme.fontSize}px`;
    }
    const fontFamily = resolveThemeFontFamily(theme);
    if (fontFamily) {
        vars['--font-sans'] = fontFamily;
    }
    if (theme.letterSpacing) {
        vars['--tracking'] = `${theme.letterSpacing}em`;
    }

    return vars;
}

/**
 * The `@font-face` rules of the fonts uploaded with the theme. Served apart
 * from the palette (which is inlined in the page so that it never flashes):
 * they are large, never change between two pages, and a font loading late is
 * exactly what `font-display: swap` is for.
 */
export function themeFontFacesCss(theme: Pick<ClientTheme, 'fonts'>): string {
    return (theme.fonts ?? [])
        .map(font => {
            const type = font.src.match(FONT_SRC_RE)?.[1];
            if (!type) {
                return '';
            }

            return (
                `@font-face{font-family:'${font.family}';` +
                `src:url(${font.src}) format('${fontFormats[type]}');` +
                `font-weight:${font.weight ?? 'normal'};` +
                `font-style:${font.style ?? 'normal'};` +
                `font-display:swap}`
            );
        })
        .join('');
}

/**
 * Every custom property to set on <html> to preview a theme in an
 * appearance: all color tokens (so that the result does not depend on the
 * preset underneath) and the style properties.
 */
export function themeToCssVars(
    theme: ClientTheme,
    mode: ThemeMode
): Record<string, string> {
    return {...themeColorVars(theme, mode), ...themeStyleVars(theme)};
}

/** Every custom property a theme may set, to clear them when leaving it */
export const THEME_CSS_VARS: string[] = [
    ...THEME_COLOR_TOKENS.map(t => `--${t}`),
    '--selection',
    '--radius',
    '--font-size',
    '--font-sans',
    '--tracking',
];

export function applyThemeVars(
    element: HTMLElement,
    vars: Record<string, string> | null
): void {
    for (const name of THEME_CSS_VARS) {
        element.style.removeProperty(name);
    }
    if (vars) {
        for (const [name, value] of Object.entries(vars)) {
            element.style.setProperty(name, value);
        }
    }
}

/** What the client needs to know about the compiled organisation theme */
export type ClientThemeMeta = {
    name: string;
    default: boolean;
    /** [background, primary] hex colors of the light palette, for the menu swatch */
    swatch: [background: string, primary: string];
};

export function themeMeta(theme: ClientTheme): ClientThemeMeta {
    const colors = resolveThemeColors(theme, 'light');

    return {
        name: theme.name,
        default: theme.default,
        swatch: [colors.background, colors.primary],
    };
}

const FONT_FAMILY_RE = /^[a-zA-Z0-9 ,'"_-]+$/;

function normalizeColors(raw: unknown): ThemeColors | null {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
        return null;
    }
    const colors: ThemeColors = {};
    for (const [token, value] of Object.entries(raw)) {
        if (!(THEME_COLOR_TOKENS as readonly string[]).includes(token)) {
            return null;
        }
        if (value === null || value === undefined || value === '') {
            continue;
        }
        if (typeof value !== 'string' || !isHexColor(value)) {
            return null;
        }
        colors[token as ThemeColorToken] = value.toLowerCase();
    }

    return colors;
}

function normalizeNumber(
    value: unknown,
    min: number,
    max: number,
    integer = false
): number | null {
    if (
        typeof value !== 'number' ||
        !Number.isFinite(value) ||
        (integer && !Number.isInteger(value)) ||
        value < min ||
        value > max
    ) {
        return null;
    }

    return value;
}

/**
 * Validates a theme coming from the stack configuration (or any untrusted
 * source) with the same rules as the API: an allow-list of keys, hex colors,
 * bounded numbers, a safe font family. Returns null when it is not a theme.
 */
export function normalizeClientTheme(raw: unknown): ClientTheme | null {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
        return null;
    }
    const input = raw as Record<string, unknown>;
    const allowed = [
        'name',
        'default',
        'colors',
        'dark',
        'radius',
        'fontSize',
        'fontFamily',
        'letterSpacing',
        'fonts',
    ];
    if (Object.keys(input).some(k => !allowed.includes(k))) {
        return null;
    }
    const name = typeof input.name === 'string' ? input.name.trim() : '';
    if (!name || name.length > THEME_LIMITS.nameMaxLength) {
        return null;
    }
    const isDefault = input.default ?? false;
    if (typeof isDefault !== 'boolean') {
        return null;
    }
    const colors = normalizeColors(input.colors ?? {});
    if (!colors) {
        return null;
    }
    const theme: ClientTheme = {name, default: isDefault, colors};

    if (input.dark !== undefined && input.dark !== null) {
        const dark = normalizeColors(input.dark);
        if (!dark) {
            return null;
        }
        theme.dark = dark;
    }
    if (input.radius !== undefined && input.radius !== null) {
        const radius = normalizeNumber(
            input.radius,
            THEME_LIMITS.radius.min,
            THEME_LIMITS.radius.max
        );
        if (radius === null) {
            return null;
        }
        theme.radius = radius;
    }
    if (input.fontSize !== undefined && input.fontSize !== null) {
        const fontSize = normalizeNumber(
            input.fontSize,
            THEME_LIMITS.fontSize.min,
            THEME_LIMITS.fontSize.max,
            true
        );
        if (fontSize === null) {
            return null;
        }
        theme.fontSize = fontSize;
    }
    if (input.letterSpacing !== undefined && input.letterSpacing !== null) {
        const letterSpacing = normalizeNumber(
            input.letterSpacing,
            THEME_LIMITS.letterSpacing.min,
            THEME_LIMITS.letterSpacing.max
        );
        if (letterSpacing === null) {
            return null;
        }
        theme.letterSpacing = letterSpacing;
    }
    if (input.fonts !== undefined && input.fonts !== null) {
        const fonts = normalizeFonts(input.fonts);
        if (!fonts) {
            return null;
        }
        if (fonts.length > 0) {
            theme.fonts = fonts;
        }
    }
    if (input.fontFamily !== undefined && input.fontFamily !== null) {
        if (typeof input.fontFamily !== 'string') {
            return null;
        }
        const fontFamily = input.fontFamily.trim();
        if (fontFamily) {
            if (
                fontFamily.length > THEME_LIMITS.fontFamilyMaxLength ||
                !FONT_FAMILY_RE.test(fontFamily)
            ) {
                return null;
            }
            theme.fontFamily = fontFamily;
        }
    }

    return theme;
}

/** The uploaded fonts of an untrusted theme, or null when one is invalid */
function normalizeFonts(raw: unknown): ClientThemeFont[] | null {
    if (!Array.isArray(raw) || raw.length > THEME_LIMITS.fonts.max) {
        return null;
    }
    const fonts: ClientThemeFont[] = [];
    for (const item of raw) {
        if (!item || typeof item !== 'object' || Array.isArray(item)) {
            return null;
        }
        const input = item as Record<string, unknown>;
        if (
            Object.keys(input).some(
                k => !['family', 'src', 'weight', 'style'].includes(k)
            )
        ) {
            return null;
        }
        const family =
            typeof input.family === 'string' ? input.family.trim() : '';
        if (
            !family ||
            family.length > THEME_LIMITS.fonts.familyMaxLength ||
            !FONT_FAMILY_NAME_RE.test(family)
        ) {
            return null;
        }
        const src = typeof input.src === 'string' ? input.src : '';
        if (
            !FONT_SRC_RE.test(src) ||
            src.length > THEME_LIMITS.fonts.srcMaxLength
        ) {
            return null;
        }
        const font: ClientThemeFont = {family, src};
        if (input.weight !== undefined && input.weight !== null) {
            const weight = String(input.weight);
            if (
                !(THEME_LIMITS.fonts.weights as readonly string[]).includes(
                    weight
                )
            ) {
                return null;
            }
            font.weight = weight;
        }
        if (input.style !== undefined && input.style !== null) {
            if (
                typeof input.style !== 'string' ||
                !(THEME_LIMITS.fonts.styles as readonly string[]).includes(
                    input.style
                )
            ) {
                return null;
            }
            font.style = input.style as ClientThemeFont['style'];
        }
        fonts.push(font);
    }

    return fonts;
}

function declarations(vars: Record<string, string>): string {
    return Object.entries(vars)
        .map(([name, value]) => `${name}:${value}`)
        .join(';');
}

/**
 * The stylesheet of a theme, shaped like the presets of globals.css: the
 * light palette and the style under `[data-theme='custom']`, the dark
 * alternative under `.dark[data-theme='custom']`.
 */
export function compileClientThemeCss(
    theme: ClientTheme,
    id = CUSTOM_THEME_ID
): string {
    const light = declarations({
        ...themeColorVars(theme, 'light'),
        ...themeStyleVars(theme),
    });
    const dark = declarations(themeColorVars(theme, 'dark'));

    return `[data-theme='${id}']{${light}}.dark[data-theme='${id}']{${dark}}`;
}
