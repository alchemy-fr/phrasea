import {AnnotationOptions, AnnotationType} from './annotationTypes.ts';
import {DrawingHandler} from './events.ts';

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
    options: AnnotationOptions;
}) {
    const a = new Path2D();
    a.arc(x, y, radius, 0, 2 * Math.PI, false);
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.stroke(a);
}

function getRadius(deltaX: number, deltaY: number) {
    return Math.abs(
        3 +
            Math.max(Math.abs(deltaX), Math.abs(deltaY)) *
                (deltaX < 0 || deltaY < 0 ? -1 : 1)
    );
}

export const CircleAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, context, options}) => {
        drawCircle({
            x,
            y,
            context,
            radius: 3,
            options,
        });
    },
    onDrawMove: ({
        clear,
        startingPoint: {x, y},
        context,
        deltaX,
        deltaY,
        options,
    }) => {
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
    drawAnnotation: ({annotation: {x, y, r, c, s}, context, toX, toY}) => {
        drawCircle({
            x: toX(x),
            y: toY(y),
            context,
            radius: toX(r),
            options: {
                color: c,
                size: toX(s),
            },
        });
    },
    onTerminate: () => {},
};
