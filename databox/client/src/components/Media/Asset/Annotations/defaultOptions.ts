import {AnnotationOptions, AnnotationType} from "./annotationTypes.ts";

type Sizes = Partial<Record<AnnotationType, number>>;
type Colors = Partial<Record<AnnotationType, string>>;

const lastSizes: Sizes = {};
const lastColors: Colors = {};

export function getDefaultSize(type: AnnotationType): number {
    const defaultStroke = 2;

    return lastSizes[type] ?? ({
        [AnnotationType.Arrow]: 5,
        [AnnotationType.Circle]: defaultStroke,
        [AnnotationType.Draw]: 10,
        [AnnotationType.Line]: defaultStroke,
        [AnnotationType.Rect]: defaultStroke,
        [AnnotationType.Target]: 10,
        [AnnotationType.Text]: 30,
    } as Sizes)[type] ?? defaultStroke;
}

function getDefaultColor(type: AnnotationType): string {
    return lastColors[type] ?? ({
        [AnnotationType.Arrow]: '#000',
        [AnnotationType.Circle]: '#000',
        [AnnotationType.Draw]: '#ff0000',
        [AnnotationType.Line]: '#000',
        [AnnotationType.Rect]: '#000',
        [AnnotationType.Target]: '#FF0000',
        [AnnotationType.Text]: '#000',
    } as Colors)[type] ?? '#000';
}

export function getDefaultOptions(type: AnnotationType): AnnotationOptions {
    return {
        color: getDefaultColor(type),
        size: getDefaultSize(type),
    };
}

export function updateLastOptions(type: AnnotationType, {size, color}: AnnotationOptions) {
    updateLastSize(type, size);
    lastColors[type] = color;
}

export function updateLastSize(type: AnnotationType, size: number) {
    lastSizes[type] = size;
}

