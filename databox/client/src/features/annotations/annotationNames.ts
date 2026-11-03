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

/** The shape is named like `getDefaultAnnotationName()` would name it */
export function isDefaultAnnotationName(
    t: TFunction,
    annotation: AssetAnnotation
): boolean {
    if (!annotation.name) {
        return false;
    }
    const placeholder = '\u0000';
    const pattern = t('annotation.default_name', '{{type}} #{{n}}', {
        type: getAnnotationTypeLabel(t, annotation.type),
        n: placeholder,
    })
        .split(placeholder)
        .map(part => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('\\d+');

    return new RegExp(`^${pattern}$`).test(annotation.name);
}

/** The name of a shape (its name, its text, else its type) */
export function getAnnotationName(
    t: TFunction,
    annotation: AssetAnnotation
): string {
    return (
        annotation.name ||
        annotation.text ||
        getAnnotationTypeLabel(t, annotation.type)
    );
}

/** `m:ss`, `h:mm:ss` from an hour: the instant of a shape of a video */
export function formatAnnotationTime(seconds: number): string {
    const total = Math.max(0, Math.floor(seconds));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = String(total % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

/**
 * The name shown for a shape away from the media: with its page, for a
 * shape of a document; with its time, for a shape of a video
 */
export function getAnnotationLabel(
    t: TFunction,
    annotation: AssetAnnotation
): string {
    const name = getAnnotationName(t, annotation);
    if (typeof annotation.t === 'number') {
        return t('annotation.at_time', '{{name}} · {{time}}', {
            name,
            time: formatAnnotationTime(annotation.t),
        });
    }
    if (!annotation.page) {
        return name;
    }

    return t('annotation.on_page', '{{name}} · p. {{page}}', {
        name,
        page: annotation.page,
    });
}
