import {CSSProperties} from 'react';

export function parseInlineStyle(style: string): CSSProperties {
    const template = document.createElement('template');
    template.setAttribute('style', style);
    return Object.entries(template.style)
        .filter(([key]) => !/^[0-9]+$/.test(key))
        .filter(([, value]) => Boolean(value))
        .reduce(
            (acc, [key, value]) => ({...acc, [key]: value}),
            {}
        ) as CSSProperties;
}
