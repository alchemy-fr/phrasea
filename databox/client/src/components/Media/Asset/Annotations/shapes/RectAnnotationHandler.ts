import {AnnotationOptions, AnnotationType, RectangleAnnotation,} from '../annotationTypes.ts';
import {DrawingHandler, OnResizeEvent} from '../events.ts';
import {drawRectangle, getMoveCircleCoordsInRectangle, normalizeRectangleProps, RectangleProps} from "./rectangle.ts";
import {isPointInCircle} from "./circle.ts";
import {controlsSize} from "./shapeCommon.ts";
import {getStandardMoveHandler} from "../common.ts";


export function isPointInRectangle(x: number, y: number, {x: rx, y: ry, w, h}: RectangleProps) {
    return x >= rx && x <= rx + w && y >= ry && y <= ry + h;
}

export const RectAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options}) => {
        drawRectangle(
            drawContext,
            {
                x,
                y,
                w: 0,
                h: 0,
            },
            options,
        );
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
        drawRectangle(
            drawContext,
            {
                x,
                y,
                w: deltaX,
                h: deltaY,
            },
            options,);
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
        x = relativeX(x);
        y = relativeY(y);
        const w = relativeX(deltaX);
        const h = relativeY(deltaY);

        const props: Partial<RectangleAnnotation> = {
            type: AnnotationType.Rect,
            c: options.color,
            s: relativeX(options.size),
            ...normalizeRectangleProps({x, y, w, h}),
        };

        onNewAnnotation(props as RectangleAnnotation);
        terminate();
    },
    drawAnnotation: ({annotation, drawContext, toX, toY}, selected) => {
        const {x, y, w, h, c, s} = annotation;
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
                size: toX(s)
            }
            , selected);
    },
    onTerminate: () => {
    },
    getResizeHandler: ({drawContext, annotation, toX, toY, x, y}) => {
        for (const [cx, cy] of [
            [0, 0],
            [1, 0],
            [0, 1],
            [1, 1],
        ]) {
            if (isPointInCircle(x, y, {
                x: toX(annotation.x + annotation.w * cx),
                y: toY(annotation.y + annotation.h * cy),
                radius: controlsSize / drawContext.zoom,
            })) {
                return ({annotation, relativeX, relativeY, deltaX, deltaY}: OnResizeEvent) => {
                    const dX = relativeX(deltaX);
                    const dY = relativeY(deltaY);

                    const xywh = {
                        x: annotation.x + dX * (1 - cx),
                        y: annotation.y + dY * (1 - cy),
                        w: annotation.w - dX * (1 - cx) + dX * cx,
                        h: annotation.h - dY * (1 - cy) + dY * cy,
                    };

                    return {
                        ...annotation,
                        ...normalizeRectangleProps(xywh)
                    };
                };
            }
        }

        if (isPointInCircle(x, y, getMoveCircleCoordsInRectangle(drawContext, {
            x: toX(annotation.x),
            y: toY(annotation.y),
            w: toX(annotation.w),
            h: toY(annotation.h),
        }))) {
            return ({annotation, relativeX, relativeY, deltaX, deltaY}) => {
                return {
                    ...annotation,
                    x: annotation.x + relativeX(deltaX),
                    y: annotation.y + relativeY(deltaY),
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
    getBoundingBox: ({annotation, toY, toX}) => {
        return {
            x: toX(annotation.x),
            y: toY(annotation.y),
            w: toX(annotation.w),
            h: toY(annotation.h),
        }
    },
    getMoveHandler: getStandardMoveHandler,
};
