import {AnnotationOptions, AnnotationType, Point} from '../annotationTypes.ts';
import {DrawingHandler} from '../events.ts';
import {controlsSize} from "./shapeCommon.ts";
import {drawCircle, drawCircleControl, isPointInCircle} from "./circle.ts";
import {drawLine} from "./line.ts";

const grow = 5;

function drawPoint(
    context: CanvasRenderingContext2D,
    {
        x,
        y,
    }: Point,
    options: AnnotationOptions, selected: boolean = false
) {
    const size = options.size;
    drawCircle(context, {
        x,
        y,
        radius: size,
    }, {
        color: options.color,
        size: size,
        fillColor: options.color,
    });
    drawCircle(context, {
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
        drawLine(context, {
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
        drawCircleControl(context, {
            x,
            y,
            radius: controlsSize,
        });
    }
}

export const PointAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, context, options}) => {
        drawPoint(
            context,
            {
                x,
                y,
            },
            options,);
    },
    onDrawMove: ({clear, context, x, y, options}) => {
        clear();
        drawPoint(
            context, {
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
    drawAnnotation: ({annotation: {x, y, c, s}, context, toX, toY}, selected) => {
        drawPoint(context,
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
            return ({annotation, relativeX, relativeY, x, y}) => {
                return {
                    ...annotation,
                    x: relativeX(x),
                    y: relativeY(y),
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
