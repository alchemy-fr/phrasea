import {CSSProperties} from 'react';

export function getRelativeViewWidth(relativeSize: number): number {
    const docHeight =
        window.innerWidth ||
        document.documentElement.clientWidth ||
        document.body.clientWidth;

    return (docHeight * relativeSize) / 100;
}

export function getRelativeViewHeight(relativeSize: number): number {
    return (getWindowHeight() * relativeSize) / 100;
}

export function getWindowHeight(): number {
    return (
        window.innerHeight ||
        document.documentElement.clientHeight ||
        document.body.clientHeight
    );
}

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
