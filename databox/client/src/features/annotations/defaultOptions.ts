import {AnnotationOptions, AnnotationType} from './types';

// The last options used per tool are kept for the session
const lastOptions: Partial<Record<AnnotationType, AnnotationOptions>> = {};

const defaultStroke = 3;

const defaultSizes: Partial<Record<AnnotationType, number>> = {
    [AnnotationType.Arrow]: 4,
    [AnnotationType.Circle]: defaultStroke,
    [AnnotationType.Draw]: 6,
    [AnnotationType.Line]: defaultStroke,
    [AnnotationType.Rect]: defaultStroke,
    [AnnotationType.Target]: 4,
    [AnnotationType.Text]: 32,
};

const defaultColor = '#e11d48';

export function getDefaultOptions(type: AnnotationType): AnnotationOptions {
    return (
        lastOptions[type] ?? {
            color: defaultColor,
            size: defaultSizes[type] ?? defaultStroke,
        }
    );
}

export function updateLastOptions(
    type: AnnotationType,
    options: AnnotationOptions
) {
    lastOptions[type] = options;
}
