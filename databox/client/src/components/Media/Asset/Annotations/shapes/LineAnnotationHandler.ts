import {
    AnnotationOptions,
    AnnotationType,
    AssetAnnotation,
} from '../annotationTypes.ts';
import {DrawingHandler, OnResizeEvent} from '../events.ts';
import {getResizeCircleCoords, isPointInCircle} from './circle.ts';
import {drawLine as baseDrawLine} from './line.ts';

export function createLineAnnotationHandler(
    drawLine: typeof baseDrawLine,
    annotationType: AnnotationType
): DrawingHandler {
    return {
        onDrawStart: ({x, y, drawContext, options}) => {
            drawLine(
                drawContext,
                {
                    x1: x,
                    y1: y,
                    x2: x,
                    y2: y,
                },
                options
            );
        },
        onDrawMove: ({
            clear,
            startingPoint: {x, y},
            drawContext,
            x: mX,
            y: mY,
            options,
        }) => {
            clear();
            drawLine(
                drawContext,
                {
                    x1: x,
                    y1: y,
                    x2: mX,
                    y2: mY,
                },
                options
            );
        },
        onDrawEnd: ({
            onNewAnnotation,
            startingPoint: {x, y},
            x: mX,
            y: mY,
            relativeX,
            relativeY,
            options,
            terminate,
        }) => {
            onNewAnnotation({
                type: annotationType,
                x1: relativeX(x),
                y1: relativeY(y),
                x2: relativeX(mX),
                y2: relativeY(mY),
                c: options.color,
                s: relativeX(options.size),
            });
            terminate();
        },
        drawAnnotation: (
            {annotation: {x1, y1, x2, y2, c, s}, drawContext, toX, toY},
            {selected, editable}
        ) => {
            drawLine(
                drawContext,
                {
                    x1: toX(x1),
                    y1: toY(y1),
                    x2: toX(x2),
                    y2: toY(y2),
                },
                {
                    color: c,
                    size: toX(s),
                },
                selected && editable
            );
        },
        onTerminate: () => {},
        getResizeHandler: ({annotation, toX, toY, x, y, drawContext}) => {
            if (
                isPointInCircle(
                    x,
                    y,
                    getResizeCircleCoords(drawContext, {
                        x: toX(annotation.x1),
                        y: toY(annotation.y1),
                        radius: 0,
                    })
                )
            ) {
                return ({annotation, relativeX, relativeY, x, y}) => {
                    return {
                        ...annotation,
                        x1: relativeX(x),
                        y1: relativeY(y),
                    };
                };
            } else if (
                isPointInCircle(
                    x,
                    y,
                    getResizeCircleCoords(drawContext, {
                        x: toX(annotation.x2),
                        y: toY(annotation.y2),
                        radius: 0,
                    })
                )
            ) {
                return ({annotation, relativeX, relativeY, x, y}) => {
                    return {
                        ...annotation,
                        x2: relativeX(x),
                        y2: relativeY(y),
                    };
                };
            }
        },
        toOptions: ({c, s}, {toX}) =>
            ({
                color: c,
                size: toX(s),
            }) as AnnotationOptions,
        fromOptions: (options, annotation, {relativeX}) => ({
            ...annotation,
            c: options.color,
            s: relativeX(options.size),
        }),
        getBoundingBox: ({annotation: {x1, y1, x2, y2}, toX, toY}) => {
            return {
                x: toX(Math.min(x1, x2)),
                y: toY(Math.min(y1, y2)),
                w: toX(Math.abs(x1 - x2)),
                h: toY(Math.abs(y1 - y2)),
            };
        },
        getMoveHandler:
            () =>
            ({
                annotation,
                deltaX,
                deltaY,
                relativeY,
                relativeX,
            }: OnResizeEvent): AssetAnnotation => {
                return {
                    ...annotation,
                    x1: annotation.x1 + relativeX(deltaX),
                    x2: annotation.x2 + relativeX(deltaX),
                    y1: annotation.y1 + relativeY(deltaY),
                    y2: annotation.y2 + relativeY(deltaY),
                };
            },
    };
}

export const LineAnnotationHandler: DrawingHandler =
    createLineAnnotationHandler(baseDrawLine, AnnotationType.Line);
