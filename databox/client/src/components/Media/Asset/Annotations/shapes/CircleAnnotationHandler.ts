import {AnnotationOptions, AnnotationType} from '../annotationTypes.ts';
import {DrawingHandler} from '../events.ts';

const controlsSize = 15;
const controlsColor = '#000';

function drawCircle(
    {
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
    },
    controls: boolean = false
) {
    const a = new Path2D();
    a.arc(x, y, radius, 0, 2 * Math.PI, false);
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.stroke(a);

    if (controls) {
        drawCircle({
            ...getMoveCircleCoords({x, y, radius}),
            context,
            options: {
                color: controlsColor,
                size: 1,
            },
        });
        drawCircle({
            ...getResizeCircleCoords({x, y, radius}),
            context,
            radius: controlsSize,
            options: {
                color: controlsColor,
                size: 1,
            },
        });
    }
}

type CircleProps = {
    x: number;
    y: number;
    radius: number;
};

function getMoveCircleCoords({x, y}: CircleProps): CircleProps {
    return {
        x,
        y,
        radius: controlsSize,
    };
}

function getResizeCircleCoords({x, y, radius}: CircleProps): CircleProps {
    return {
        x: x + radius,
        y,
        radius: controlsSize,
    };
}

function isPointInCircle(
    x: number,
    y: number,
    {x: cx, y: cy, radius}: CircleProps
) {
    return Math.sqrt((x - cx) ** 2 + (y - cy) ** 2) < radius;
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
    drawAnnotation: (
        {annotation: {x, y, r, c, s}, context, toX, toY},
        selected
    ) => {
        drawCircle(
            {
                x: toX(x),
                y: toY(y),
                context,
                radius: toX(r),
                options: {
                    color: c,
                    size: toX(s),
                },
            },
            selected
        );
    },
    onTerminate: () => {},
    isPointInside: ({annotation, x, y, toX, toY}) => {
        return isPointInCircle(x, y, {
            x: toX(annotation.x),
            y: toY(annotation.y),
            radius: toX(annotation.r),
        });
    },
    getResizeHandler: ({annotation, toX, toY, x, y}) => {
        if (
            isPointInCircle(
                x,
                y,
                getMoveCircleCoords({
                    x: toX(annotation.x),
                    y: toY(annotation.y),
                    radius: toX(annotation.r),
                })
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
};
