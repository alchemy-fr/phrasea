import {AnnotationOptions, AnnotationType} from '../types';
import type {DrawingHandler} from '../handlerTypes';
import {
    drawCircle,
    getMoveCircleCoordsInCircle,
    getResizeCircleCoords,
    isPointInCircle,
} from './circle';
import {getStandardMoveHandler} from '../common';

function getRadius(deltaX: number, deltaY: number) {
    return Math.abs(
        3 +
            Math.max(Math.abs(deltaX), Math.abs(deltaY)) *
                (deltaX < 0 || deltaY < 0 ? -1 : 1)
    );
}

export const CircleAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options}) => {
        drawCircle(drawContext, {x, y, radius: 3}, options);
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
        drawCircle(
            drawContext,
            {x, y, radius: getRadius(deltaX, deltaY)},
            options
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
        {annotation: {x, y, r, c, s, f}, drawContext, toX, toY},
        {selected, editable}
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
                fillColor: f,
                size: toX(s),
            },
            selected && editable
        );
    },
    onTerminate: () => {},
    getResizeHandler: ({annotation, toX, toY, x, y, drawContext}) => {
        const circle = {
            x: toX(annotation.x),
            y: toY(annotation.y),
            radius: toX(annotation.r),
        };
        if (
            isPointInCircle(
                x,
                y,
                getMoveCircleCoordsInCircle(drawContext, circle)
            )
        ) {
            return getStandardMoveHandler();
        } else if (
            isPointInCircle(x, y, getResizeCircleCoords(drawContext, circle))
        ) {
            return ({annotation, relativeX, deltaX}) => ({
                ...annotation,
                r: Math.max(annotation.r + relativeX(deltaX), relativeX(3)),
            });
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
    getBoundingBox: ({annotation: {x, y, r}, toX, toY}) => {
        const radius = toX(r);

        return {
            x: toX(x) - radius,
            y: toY(y) - radius,
            w: 2 * radius,
            h: 2 * radius,
        };
    },
    getMoveHandler: getStandardMoveHandler,
};
