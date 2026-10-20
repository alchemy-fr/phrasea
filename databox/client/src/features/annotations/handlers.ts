import {AnnotationType} from './types';
import type {DrawingHandler} from './handlerTypes';
import {DrawAnnotationHandler} from './shapes/DrawAnnotationHandler';
import {RectAnnotationHandler} from './shapes/RectAnnotationHandler';
import {TargetAnnotationHandler} from './shapes/TargetAnnotationHandler';
import {CircleAnnotationHandler} from './shapes/CircleAnnotationHandler';
import {LineAnnotationHandler} from './shapes/LineAnnotationHandler';
import {ArrowAnnotationHandler} from './shapes/ArrowAnnotationHandler';
import {TextAnnotationHandler} from './shapes/TextAnnotationHandler';

/** The shapes drawn over an image. Time based ones (cue, range) have no tool. */
export const drawingHandlers: Partial<Record<AnnotationType, DrawingHandler>> =
    {
        [AnnotationType.Circle]: CircleAnnotationHandler,
        [AnnotationType.Target]: TargetAnnotationHandler,
        [AnnotationType.Rect]: RectAnnotationHandler,
        [AnnotationType.Draw]: DrawAnnotationHandler,
        [AnnotationType.Line]: LineAnnotationHandler,
        [AnnotationType.Arrow]: ArrowAnnotationHandler,
        [AnnotationType.Text]: TextAnnotationHandler,
    };

export function getDrawingHandler(
    type: string | undefined
): DrawingHandler | undefined {
    return type ? drawingHandlers[type as AnnotationType] : undefined;
}
