import type {AssetAnnotation} from './types';

/** Drops the `editable` flag older clients stored with their shapes */
export function toStoredAnnotation(
    annotation: AssetAnnotation
): AssetAnnotation {
    const {editable: _editable, ...stored} = annotation;

    return stored;
}

/**
 * Shapes are selected and updated by id; the ones stored without any (an
 * integration wrote them) get one, kept once the list is edited.
 */
export function ensureAnnotationIds(
    annotations: AssetAnnotation[],
    prefix: string
): AssetAnnotation[] {
    return annotations.map((a, i) => (a.id ? a : {...a, id: `${prefix}-${i}`}));
}

/** The page of a document a shape is on: the first one when it has none */
export function getAnnotationPage(annotation: AssetAnnotation): number {
    return annotation.page ?? 1;
}

/** The shape is on the page; every shape is when no page is given */
export function isOnPage(
    annotation: AssetAnnotation,
    page: number | undefined
): boolean {
    return page === undefined || getAnnotationPage(annotation) === page;
}
