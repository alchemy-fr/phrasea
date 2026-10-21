import type {TFunction} from 'i18next';
import {AnnotationType, type AssetAnnotation} from './types';

export function getAnnotationTypeLabel(t: TFunction, type: string): string {
    const labels: Record<AnnotationType, string> = {
        [AnnotationType.Draw]: t('annotation.type.draw', 'Drawing'),
        [AnnotationType.Line]: t('annotation.type.line', 'Line'),
        [AnnotationType.Arrow]: t('annotation.type.arrow', 'Arrow'),
        [AnnotationType.Text]: t('annotation.type.text', 'Text'),
        [AnnotationType.Cue]: t('annotation.type.cue', 'Cue'),
        [AnnotationType.Circle]: t('annotation.type.circle', 'Circle'),
        [AnnotationType.Rect]: t('annotation.type.rectangle', 'Rectangle'),
        [AnnotationType.Face]: t('annotation.type.face', 'Face'),
        [AnnotationType.Target]: t('annotation.type.target', 'Target'),
        [AnnotationType.TimeRange]: t(
            'annotation.type.timerange',
            'Time range'
        ),
    };

    return labels[type as AnnotationType] ?? type;
}

/** "Rectangle #2": the next number among the shapes of the same type and page */
export function getDefaultAnnotationName(
    t: TFunction,
    annotation: AssetAnnotation,
    others: AssetAnnotation[]
): string {
    return t('annotation.default_name', '{{type}} #{{n}}', {
        type: getAnnotationTypeLabel(t, annotation.type),
        n:
            others.filter(
                a => a.type === annotation.type && a.page === annotation.page
            ).length + 1,
    });
}

/** The name shown for a shape (its text, its name, else its type) */
export function getAnnotationLabel(
    t: TFunction,
    annotation: AssetAnnotation
): string {
    return (
        annotation.name ||
        annotation.text ||
        getAnnotationTypeLabel(t, annotation.type)
    );
}
