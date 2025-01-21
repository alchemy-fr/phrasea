import {AnnotationOptions, AnnotationType} from '../annotationTypes.ts';
import {DrawingHandler} from '../events.ts';
import {getResizeCircleCoords, isPointInCircle} from "./circle.ts";
import {drawLine as baseDrawLine, getLineMoveCircleCoords, isPointInLine} from "./line.ts";

export function createLineAnnotationHandler(): DrawingHandler {
    const drawLine: typeof baseDrawLine = (drawContext, line, options, selected = false) => {
        const {x1, y1, x2, y2} = line;
        const openingAngle = Math.PI / 6;
        const arrowSize = 7 * options.size;

        const dx = x2 - x1;
        const dy = y2 - y1;
        const angle = Math.atan2(dy, dx);

        baseDrawLine(drawContext, {
            x1: x2 - arrowSize * Math.cos(angle - openingAngle),
            y1: y2 - arrowSize * Math.sin(angle - openingAngle),
            x2: x2,
            y2: y2,
        }, options);

        baseDrawLine(drawContext, {
            x1: x2 - arrowSize * Math.cos(angle + openingAngle),
            y1: y2 - arrowSize * Math.sin(angle + openingAngle),
            x2: x2,
            y2: y2,
        }, options);

        baseDrawLine(drawContext, line, options, selected);
    }

    return {
        onDrawStart: ({x, y, drawContext, options}) => {
            drawLine(drawContext, {
                x1: x,
                y1: y,
                x2: x,
                y2: y,
            }, options);
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
            drawLine(drawContext, {
                    x1: x,
                    y1: y,
                    x2: mX,
                    y2: mY,
                },
                options,
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
                type: AnnotationType.Line,
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
            selected
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
                selected
            );
        },
        onTerminate: () => {
        },
        isPointInside: ({annotation, x, y, toX, toY}) => {
            return isPointInLine(x, y, {
                x1: toX(annotation.x1),
                y1: toY(annotation.y1),
                x2: toX(annotation.x2),
                y2: toY(annotation.y2),
            });
        },
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
            } else if (isPointInCircle(
                x,
                y,
                getLineMoveCircleCoords(drawContext, {
                    x1: toX(annotation.x1),
                    y1: toY(annotation.y1),
                    x2: toX(annotation.x2),
                    y2: toY(annotation.y2),
                }))) {
                return ({annotation, relativeX, relativeY, deltaX, deltaY}) => {
                    const dX = relativeX(deltaX);
                    const dY = relativeY(deltaY);

                    return {
                        ...annotation,
                        x1: annotation.x1 + dX,
                        y1: annotation.y1 + dY,
                        x2: annotation.x2 + dX,
                        y2: annotation.y2 + dY,
                    };
                };
            }
        },
        toOptions: ({c, s}, {toX}) => ({
            color: c,
            size: toX(s),
        } as AnnotationOptions),
        fromOptions: (options, annotation, {relativeX}) => ({
            ...annotation,
            c: options.color,
            s: relativeX(options.size),
        }),
    };
}

export const LineAnnotationHandler: DrawingHandler = createLineAnnotationHandler();
