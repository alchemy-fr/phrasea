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

/**
 * The instant a video shows its shapes at: a shape drawn on a frame (with a
 * `t`) is shown while the video is paused on that frame only
 */
export type AnnotationTimeline = {
    /** The instant shown, undefined while playing */
    time: number | undefined;
    /** Seconds away from `time` a shape is still on the frame shown */
    tolerance: number;
    /** The current time of the media, given to the shapes created */
    now: () => number;
};

/** Seconds of a frame when the frame rate of a video is unknown (25 fps) */
export const defaultFrameDuration = 0.04;

/**
 * The shape is on the frame shown: every shape is without a timeline, and
 * the ones without a time on every frame
 */
export function isAtTime(
    annotation: AssetAnnotation,
    timeline: Pick<AnnotationTimeline, 'time' | 'tolerance'> | undefined
): boolean {
    if (!timeline || typeof annotation.t !== 'number') {
        return true;
    }

    return (
        timeline.time !== undefined &&
        Math.abs(timeline.time - annotation.t) <= timeline.tolerance + 1e-6
    );
}
