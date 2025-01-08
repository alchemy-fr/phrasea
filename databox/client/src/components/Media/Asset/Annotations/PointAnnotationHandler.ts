import {AnnotationOptions, AnnotationType} from "./annotationTypes.ts";
import {DrawingHandler} from "./events.ts";

function drawPoint({
    x,
    y,
    context,
    options,
}: {
    x: number;
    y: number;
    context: CanvasRenderingContext2D;
    options: AnnotationOptions,
}) {
    const a = new Path2D();
    a.arc(x, y, options.size, 0, 2 * Math.PI, false);
    context.fillStyle = options.color;
    context.fill(a);
}


export const PointAnnotationHandler: DrawingHandler = {
    onStart: ({x, y, context, options}) => {
        drawPoint({
            x,
            y,
            context,
            options,
        });
    },
    onMove: ({clear, context, x, y, options}) => {
        clear();
        drawPoint({
            x,
            y,
            context,
            options,
        });
    },
    onEnd: ({onNewAnnotation, x, y, relativeX, relativeY, options}) => {
        onNewAnnotation({
            type: AnnotationType.Point,
            x: relativeX(x),
            y: relativeY(y),
            c: options.color,
            s: relativeX(options.size),
        });
    },
    drawAnnotation: ({annotation: {
        x,
        y,
        c,
        s,
    }, context, toX, toY}) => {
        drawPoint({
            x: toX(x),
            y: toY(y),
            context,
            options: {
                color: c,
                size: toX(s),
            },
        });
    }
};
