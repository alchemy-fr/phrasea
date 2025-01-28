import {AnnotationOptions, AnnotationType, Point} from '../annotationTypes.ts';
import {DrawContext, DrawingHandler} from '../events.ts';
import {drawCircle} from './circle.ts';
import {drawLine} from './line.ts';
import {getStandardMoveHandler} from '../common.ts';

const grow = 5;

function drawTarget(
    drawContext: DrawContext,
    {x, y}: Point,
    options: AnnotationOptions,
    _controls: boolean = false
) {
    const size = options.size;
    drawCircle(
        drawContext,
        {
            x,
            y,
            radius: size,
        },
        {
            color: options.color,
            size: size,
            fillColor: options.color,
        }
    );
    drawCircle(
        drawContext,
        {
            x,
            y,
            radius: size * grow,
        },
        {
            color: options.color,
            size: size,
        }
    );

    const radius = size * grow;
    const lineLength = radius / 2;
    for (const [cx, cy] of [
        [-1, 0],
        [1, 0],
        [0, 1],
        [0, -1],
    ]) {
        drawLine(
            drawContext,
            {
                x1: x + radius * cx,
                y1: y + radius * cy,
                x2: x + radius * cx + lineLength * cx,
                y2: y + radius * cy + lineLength * cy,
            },
            {
                color: options.color,
                size: size,
            }
        );
    }
}

export const TargetAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options}) => {
        drawTarget(
            drawContext,
            {
                x,
                y,
            },
            options
        );
    },
    onDrawMove: ({clear, drawContext, x, y, options}) => {
        clear();
        drawTarget(
            drawContext,
            {
                x,
                y,
            },
            options
        );
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
            type: AnnotationType.Target,
            x: relativeX(x),
            y: relativeY(y),
            c: options.color,
            s: relativeX(options.size),
        });
        terminate();
    },
    drawAnnotation: (
        {annotation: {x, y, c, s}, drawContext, toX, toY},
        {selected, editable}
    ) => {
        drawTarget(
            drawContext,
            {
                x: toX(x),
                y: toY(y),
            },
            {
                color: c,
                size: toX(s),
            },
            selected && editable
        );
    },
    onTerminate: () => {},
    getResizeHandler: () => {
        return undefined;
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
    getBoundingBox: ({annotation: {x, y}, options: {size}, toX, toY}) => {
        const radius = size * grow;

        return {
            x: toX(x) - radius,
            y: toY(y) - radius,
            w: radius * 2,
            h: radius * 2,
        };
    },
    getMoveHandler: getStandardMoveHandler,
};
