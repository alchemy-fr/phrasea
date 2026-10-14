import {AnnotationOptions, AnnotationType} from './types';

type Sizes = Partial<Record<AnnotationType, number>>;
type Colors = Partial<Record<AnnotationType, string>>;

// The last options used per tool are kept for the session
const lastSizes: Sizes = {};
const lastColors: Colors = {};

const defaultStroke = 2;

const defaultSizes: Sizes = {
    [AnnotationType.Arrow]: 5,
    [AnnotationType.Circle]: defaultStroke,
    [AnnotationType.Draw]: 10,
    [AnnotationType.Line]: defaultStroke,
    [AnnotationType.Rect]: defaultStroke,
    [AnnotationType.Target]: 10,
    [AnnotationType.Text]: 30,
};

const defaultColors: Colors = {
    [AnnotationType.Arrow]: '#000000',
    [AnnotationType.Circle]: '#000000',
    [AnnotationType.Draw]: '#ff0000',
    [AnnotationType.Line]: '#000000',
    [AnnotationType.Rect]: '#000000',
    [AnnotationType.Target]: '#ff0000',
    [AnnotationType.Text]: '#000000',
};

export function getDefaultSize(type: AnnotationType): number {
    return lastSizes[type] ?? defaultSizes[type] ?? defaultStroke;
}

function getDefaultColor(type: AnnotationType): string {
    return lastColors[type] ?? defaultColors[type] ?? '#000000';
}

export function getDefaultOptions(type: AnnotationType): AnnotationOptions {
    return {
        color: getDefaultColor(type),
        size: getDefaultSize(type),
    };
}

export function updateLastOptions(
    type: AnnotationType,
    {size, color}: AnnotationOptions
) {
    updateLastSize(type, size);
    lastColors[type] = color;
}

export function updateLastSize(type: AnnotationType, size: number) {
    lastSizes[type] = size;
}
