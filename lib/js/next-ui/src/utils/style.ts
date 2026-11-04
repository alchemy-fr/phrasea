import type {CSSProperties} from 'react';

/** `margin: 4px; max-height: 20px` => React style object */
export function parseInlineStyle(
    style: string | undefined
): CSSProperties | undefined {
    if (!style) {
        return undefined;
    }
    const result: Record<string, string> = {};
    for (const declaration of style.split(';')) {
        const [prop, ...rest] = declaration.split(':');
        const value = rest.join(':').trim();
        if (prop?.trim() && value) {
            result[
                prop
                    .trim()
                    .replace(/-([a-z])/g, (_, c: string) => c.toUpperCase())
            ] = value;
        }
    }

    return result;
}
