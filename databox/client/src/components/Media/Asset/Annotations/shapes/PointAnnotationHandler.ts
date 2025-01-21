import {AnnotationOptions, AnnotationType, Point} from '../annotationTypes.ts';
import {DrawContext, DrawingHandler} from '../events.ts';
import {controlsSize} from "./shapeCommon.ts";
import {drawCircle, drawCircleControl, isPointInCircle} from "./circle.ts";
import {drawLine} from "./line.ts";

const grow = 5;

function drawPoint(
    drawContext: DrawContext,
    {
        x,
        y,
    }: Point,
    options: AnnotationOptions, selected: boolean = false
) {
    const size = options.size;
    drawCircle(drawContext, {
        x,
        y,
        radius: size,
    }, {
        color: options.color,
        size: size,
        fillColor: options.color,
    });
    drawCircle(drawContext, {
        x,
        y,
        radius: size * grow,
    }, {
        color: options.color,
        size: size,
    });

    const radius = size * grow;
    const lineLength = radius / 2;
    for (const [cx, cy] of [
        [-1, 0],
        [1, 0],
        [0, 1],
        [0, -1],
    ]) {
        drawLine(drawContext, {
            x1: x + radius * cx,
            y1: y + radius * cy,
            x2: x + radius * cx + lineLength * cx,
            y2: y + radius * cy + lineLength * cy,
        }, {
            color: options.color,
            size: size,
        });
    }

    if (selected) {
        drawCircleControl(drawContext, {
            x,
            y,
            radius: controlsSize / drawContext.zoom,
        });
    }
}

export const PointAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options}) => {
        drawPoint(
            drawContext,
            {
                x,
                y,
            },
            options,);
    },
    onDrawMove: ({clear, drawContext, x, y, options}) => {
        clear();
        drawPoint(
            drawContext, {
                x,
                y
            },
            options,);
    },
    onDrawEnd: ({
        onNewAnnotation,
        x,
        y,
        relativeX,
        relativeY,
        options,
        terminate,
    }) => {
        onNewAnnotation({
            type: AnnotationType.Point,
            x: relativeX(x),
            y: relativeY(y),
            c: options.color,
            s: relativeX(options.size),
        });
        terminate();
    },
    drawAnnotation: ({annotation: {x, y, c, s}, drawContext, toX, toY}, selected) => {
        drawPoint(drawContext,
            {
                x: toX(x),
                y: toY(y)
            },
            {
                color: c,
                size: toX(s),
            }, selected);
    },
    onTerminate: () => {
    },
    isPointInside: ({annotation, x, y, toX, toY}) => {
        return isPointInCircle(x, y, {
            x: toX(annotation.x),
            y: toY(annotation.y),
            radius: Math.max(toX(annotation.s) * grow, controlsSize),
        });
    },
    getResizeHandler: ({annotation, toX, toY, x, y}) => {
        if (
            isPointInCircle(
                x,
                y,
                {
                    x: toX(annotation.x),
                    y: toY(annotation.y),
                    radius: controlsSize,
                }
            )
        ) {
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
};
