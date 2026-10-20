import {AnnotationOptions, AnnotationType, RectangleAnnotation} from '../types';
import type {DrawingHandler, OnResizeEvent} from '../handlerTypes';
import {
    drawRectangle,
    getMoveCircleCoordsInRectangle,
    isPointInRectangle,
    normalizeRectangleProps,
} from './rectangle';
import {isPointInCircle} from './circle';
import {controlsSize} from '../controls';
import {getStandardMoveHandler} from '../common';

export {isPointInRectangle};

export const RectAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options}) => {
        drawRectangle(drawContext, {x, y, w: 0, h: 0}, options);
    },
    onDrawMove: ({
        clear,
        drawContext,
        startingPoint: {x, y},
        deltaY,
        deltaX,
        options,
    }) => {
        clear();
        drawRectangle(drawContext, {x, y, w: deltaX, h: deltaY}, options);
    },
    onDrawEnd: ({
        onNewAnnotation,
        startingPoint: {x, y},
        deltaY,
        deltaX,
        relativeX,
        relativeY,
        options,
        terminate,
    }) => {
        const props: Partial<RectangleAnnotation> = {
            type: AnnotationType.Rect,
            c: options.color,
            s: relativeX(options.size),
            ...normalizeRectangleProps({
                x: relativeX(x),
                y: relativeY(y),
                w: relativeX(deltaX),
                h: relativeY(deltaY),
            }),
        };

        onNewAnnotation(props as RectangleAnnotation);
        terminate();
    },
    drawAnnotation: (
        {annotation, drawContext, toX, toY},
        {selected, editable}
    ) => {
        const {x, y, w, h, c, s, f} = annotation;
        drawRectangle(
            drawContext,
            {
                x: toX(x),
                y: toY(y),
                w: toX(w),
                h: toY(h),
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
    getResizeHandler: ({drawContext, annotation, toX, toY, x, y}) => {
        for (const [cx, cy] of [
            [0, 0],
            [1, 0],
            [0, 1],
            [1, 1],
        ]) {
            if (
                isPointInCircle(x, y, {
                    x: toX(annotation.x + annotation.w * cx),
                    y: toY(annotation.y + annotation.h * cy),
                    radius: controlsSize / drawContext.zoom,
                })
            ) {
                return ({
                    annotation,
                    relativeX,
                    relativeY,
                    deltaX,
                    deltaY,
                }: OnResizeEvent) => {
                    const dX = relativeX(deltaX);
                    const dY = relativeY(deltaY);

                    return {
                        ...annotation,
                        ...normalizeRectangleProps({
                            x: annotation.x + dX * (1 - cx),
                            y: annotation.y + dY * (1 - cy),
                            w: annotation.w - dX * (1 - cx) + dX * cx,
                            h: annotation.h - dY * (1 - cy) + dY * cy,
                        }),
                    };
                };
            }
        }

        if (
            isPointInCircle(
                x,
                y,
                getMoveCircleCoordsInRectangle(drawContext, {
                    x: toX(annotation.x),
                    y: toY(annotation.y),
                    w: toX(annotation.w),
                    h: toY(annotation.h),
                })
            )
        ) {
            return getStandardMoveHandler();
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
    getBoundingBox: ({annotation, toY, toX}) => ({
        x: toX(annotation.x),
        y: toY(annotation.y),
        w: toX(annotation.w),
        h: toY(annotation.h),
    }),
    getMoveHandler: getStandardMoveHandler,
};
