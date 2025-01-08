import {AnnotationOptions, AnnotationType} from "./annotationTypes.ts";
import {DrawingHandler} from "./events.ts";


function drawCircle({
    x,
    y,
    context,
    radius,
    options,
}: {
    x: number;
    y: number;
    context: CanvasRenderingContext2D;
    radius: number;
    options: AnnotationOptions,
}) {
    const a = new Path2D();
    a.arc(x, y, radius, 0, 2 * Math.PI, false);
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.stroke(a);
}

function getRadius(deltaX: number, deltaY: number) {
    return Math.abs(3 + Math.max(Math.abs(deltaX), Math.abs(deltaY)) * (deltaX < 0 || deltaY < 0 ? -1 : 1));
}

export const CircleAnnotationHandler: DrawingHandler = {
    onStart: ({x, y, context, options}) => {
        drawCircle({
            x,
            y,
            context,
            radius: 3,
            options,
        });
    },
    onMove: ({clear, startingPoint: {x, y}, context, deltaX, deltaY, options}) => {
        clear();
        const radius = getRadius(deltaX, deltaY);
        drawCircle({
            x,
            y,
            context,
            radius,
            options,
        });
    },
    onEnd: ({onNewAnnotation, startingPoint: {x, y}, deltaX, deltaY, relativeX, relativeY, options}) => {
        onNewAnnotation({
            type: AnnotationType.Circle,
            x: relativeX(x),
            y: relativeY(y),
            r: relativeX(getRadius(deltaX, deltaY)),
            c: options.color,
            s: options.size,
        });
    },
    drawAnnotation: ({
        annotation: {
            x,
            y,
            r,
            c,
            s
        }, context
    }) => {
        drawCircle({
            x,
            y,
            context,
            radius: r,
            options: {
                color: c,
                size: s,
            },
        });
    }
};
