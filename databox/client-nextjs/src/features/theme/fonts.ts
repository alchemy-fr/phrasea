/**
 * The Google fonts offered to the themes. They are self-hosted by Next (see
 * app/fonts.ts), which renames the families, so a theme refers to one by its
 * id and the CSS uses the custom property Next defines — never the family
 * name, which does not exist on its own.
 */
export type ThemeFont = {
    /** What a theme stores in `fontFamily` */
    id: string;
    label: string;
    /** The custom property defined by app/fonts.ts */
    cssVar: string;
    /** Used while the font loads, and when it cannot be */
    fallback: string;
};

export const themeFonts: ThemeFont[] = [
    {
        id: 'Inter',
        label: 'Inter',
        cssVar: '--font-inter',
        fallback: "system-ui, -apple-system, 'Segoe UI', sans-serif",
    },
    {
        id: 'Roboto',
        label: 'Roboto',
        cssVar: '--font-roboto',
        fallback: "'Helvetica Neue', Helvetica, Arial, sans-serif",
    },
    {
        id: 'Open Sans',
        label: 'Open Sans',
        cssVar: '--font-open-sans',
        fallback: 'Verdana, Geneva, Tahoma, sans-serif',
    },
    {
        id: 'Nunito',
        label: 'Nunito',
        cssVar: '--font-nunito',
        fallback: "'Trebuchet MS', 'Segoe UI', Tahoma, sans-serif",
    },
    {
        id: 'Manrope',
        label: 'Manrope',
        cssVar: '--font-manrope',
        fallback: "'Avenir Next', Avenir, 'Segoe UI', system-ui, sans-serif",
    },
    {
        id: 'Work Sans',
        label: 'Work Sans',
        cssVar: '--font-work-sans',
        fallback:
            "'Gill Sans', 'Gill Sans MT', Calibri, 'Segoe UI', sans-serif",
    },
    {
        id: 'Raleway',
        label: 'Raleway',
        cssVar: '--font-raleway',
        fallback: "Optima, Candara, 'Segoe UI', Calibri, sans-serif",
    },
    {
        id: 'Lora',
        label: 'Lora',
        cssVar: '--font-lora',
        fallback: "Georgia, 'Times New Roman', Times, serif",
    },
    {
        id: 'Source Serif 4',
        label: 'Source Serif 4',
        cssVar: '--font-source-serif',
        fallback: "'Iowan Old Style', Palatino, 'Book Antiqua', serif",
    },
    {
        id: 'JetBrains Mono',
        label: 'JetBrains Mono',
        cssVar: '--font-jetbrains-mono',
        fallback: "ui-monospace, 'SF Mono', Menlo, Consolas, monospace",
    },
];

export function findThemeFont(id: string | undefined): ThemeFont | undefined {
    return themeFonts.find(f => f.id === id);
}

/** The CSS font list of a theme font */
export function themeFontStack(font: ThemeFont): string {
    return `var(${font.cssVar}), ${font.fallback}`;
}
