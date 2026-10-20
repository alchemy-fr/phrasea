import type {AssetAnnotation, ZoomRef} from './types';
import type {AnnotationResizeHandler, OnResizeEvent} from './handlerTypes';

export function getZoomFromRef(zoomRef: ZoomRef): number {
    return zoomRef.current || 1;
}

/** Moves a shape positioned by its `x` / `y` */
export const getStandardMoveHandler =
    (): AnnotationResizeHandler =>
    ({
        annotation,
        deltaX,
        deltaY,
        relativeY,
        relativeX,
    }: OnResizeEvent): AssetAnnotation => {
        return {
            ...annotation,
            x: (annotation.x || 0) + relativeX(deltaX),
            y: (annotation.y || 0) + relativeY(deltaY),
        };
    };

/** Removes the local (per session) flags before a shape is stored */
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
