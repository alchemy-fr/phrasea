export type MapColors = {
    primary: string;
    primaryForeground: string;
    background: string;
};

/**
 * The value of a theme color variable in a format the map understands: the
 * theme uses `oklch()`, the map style specification only knows sRGB colors.
 */
function cssVariableColor(name: string, fallback: string): string {
    try {
        const value = getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim();
        const ctx = document
            .createElement('canvas')
            .getContext('2d', {willReadFrequently: true});
        if (!value || !ctx) {
            return fallback;
        }
        ctx.fillStyle = value;
        ctx.fillRect(0, 0, 1, 1);
        const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;

        return `rgba(${r}, ${g}, ${b}, ${Math.round((a / 255) * 100) / 100})`;
    } catch {
        return fallback;
    }
}

export function resolveMapColors(): MapColors {
    return {
        primary: cssVariableColor('--primary', '#3b63d8'),
        primaryForeground: cssVariableColor('--primary-foreground', '#ffffff'),
        background: cssVariableColor('--background', '#ffffff'),
    };
}
