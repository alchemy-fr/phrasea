import {AnnotationOptions, AnnotationType} from '../annotationTypes.ts';
import {DrawingHandler} from '../events.ts';
import {drawCircle, getMoveCircleCoordsInCircle, getResizeCircleCoords, isPointInCircle} from "./circle.ts";

function getRadius(deltaX: number, deltaY: number) {
    return Math.abs(
        3 +
        Math.max(Math.abs(deltaX), Math.abs(deltaY)) *
        (deltaX < 0 || deltaY < 0 ? -1 : 1)
    );
}

export const CircleAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options}) => {
        drawCircle(drawContext, {
            x,
            y,
            radius: 3,
        }, options);
    },
    onDrawMove: ({
        clear,
        startingPoint: {x, y},
        drawContext,
        deltaX,
        deltaY,
        options,
    }) => {
        clear();
        const radius = getRadius(deltaX, deltaY);
        drawCircle(drawContext, {
            x,
            y,
            radius,
        },
            options,
        );
    },
    onDrawEnd: ({
        onNewAnnotation,
        startingPoint: {x, y},
        deltaX,
        deltaY,
        relativeX,
        relativeY,
        options,
        terminate,
    }) => {
        onNewAnnotation({
            type: AnnotationType.Circle,
            x: relativeX(x),
            y: relativeY(y),
            r: relativeX(getRadius(deltaX, deltaY)),
            c: options.color,
            s: relativeX(options.size),
        });
        terminate();
    },
    drawAnnotation: (
        {annotation: {x, y, r, c, s}, drawContext, toX, toY},
        selected
    ) => {
        drawCircle(
            drawContext,
            {
                x: toX(x),
                y: toY(y),
                radius: toX(r),
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
        return isPointInCircle(x, y, {
            x: toX(annotation.x),
            y: toY(annotation.y),
            radius: toX(annotation.r),
        });
    },
    getResizeHandler: ({annotation, toX, toY, x, y, drawContext}) => {
        if (
            isPointInCircle(
                x,
                y,
                getMoveCircleCoordsInCircle(drawContext, {
                    x: toX(annotation.x),
                    y: toY(annotation.y),
                    radius: toX(annotation.r),
                })
            )
        ) {
            return ({annotation, relativeX, relativeY, deltaX, deltaY}) => {
                return {
                    ...annotation,
                    x: annotation.x + relativeX(deltaX),
                    y: annotation.y + relativeY(deltaY),
                };
            };
        } else if (
            isPointInCircle(
                x,
                y,
                getResizeCircleCoords(drawContext, {
                    x: toX(annotation.x),
                    y: toY(annotation.y),
                    radius: toX(annotation.r),
                })
            )
        ) {
            return ({annotation, relativeX, deltaX}) => {
                return {
                    ...annotation,
                    r: Math.max(annotation.r + relativeX(deltaX), relativeX(3)),
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
