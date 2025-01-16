import {AnnotationOptions, AnnotationType, RectangleAnnotation,} from '../annotationTypes.ts';
import {DrawingHandler} from '../events.ts';
import {drawRectangle, normalizeRectangleProps, RectangleProps} from "./rectangle.ts";
import {isPointInCircle} from "./circle.ts";
import {controlsSize} from "./shapeCommon.ts";


function isPointInRectangle(x: number, y: number, {x: rx, y: ry, w, h}: RectangleProps) {
    return x >= rx && x <= rx + w && y >= ry && y <= ry + h;
}

export const RectAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, context, options}) => {
        drawRectangle(
            context,
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
        context,
        startingPoint: {x, y},
        deltaY,
        deltaX,
        options,
    }) => {
        clear();
        drawRectangle(
            context,
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
    drawAnnotation: ({annotation, context, toX, toY}, selected) => {
        const {x, y, w, h, c, s} = annotation;
        drawRectangle(
            context,
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
    isPointInside: ({annotation, x, y, toX, toY}) => {
        return isPointInRectangle(x, y, {
            x: toX(annotation.x),
            y: toY(annotation.y),
            w: toX(annotation.w),
            h: toY(annotation.h),
        });
    },
    getResizeHandler: ({annotation, toX, toY, x, y}) => {
        for (const [cx, cy] of [
            [0, 0],
            [1, 0],
            [0, 1],
            [1, 1],
        ]) {
            if (isPointInCircle(x, y, {
                x: toX(annotation.x + annotation.w * cx),
                y: toY(annotation.y + annotation.h * cy),
                radius: controlsSize,
            })) {
                return ({annotation, relativeX, relativeY, x, y}) => {
                    const rX = relativeX(x);
                    const rY = relativeY(y);
                    const deltaX = rX - annotation.x;
                    const deltaY = rY - annotation.y;

                    const xywh = {
                        x: annotation.x + deltaX * (1 - cx),
                        y: annotation.y + deltaY * (1 - cy),
                        w: annotation.w - deltaX * (1 - cx) + (deltaX - annotation.w) * cx,
                        h: annotation.h - deltaY * (1 - cy) + (deltaY - annotation.h) * cy,
                    };

                    return {
                        ...annotation,
                        ...normalizeRectangleProps(xywh)
                    };
                };
            }
        }

        if (isPointInCircle(x, y, {
            x: toX(annotation.x + annotation.w / 2),
            y: toY(annotation.y + annotation.h / 2),
            radius: controlsSize,
        })) {
            console.log('x, y', x, y);
            return ({annotation, relativeX, relativeY, x, y}) => {
                return {
                    ...annotation,
                    x: relativeX(x) - annotation.w / 2,
                    y: relativeY(y) - annotation.h / 2,
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
