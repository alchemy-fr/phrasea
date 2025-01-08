import {AnnotationOptions, AnnotationType, RectangleAnnotation} from "./annotationTypes.ts";
import {DrawingHandler} from "./events.ts";

function drawRectangle({
    x,
    y,
    w,
    h,
    context,
    options,
}: {
    x: number;
    y: number;
    w: number;
    h: number;
    context: CanvasRenderingContext2D;
    options: AnnotationOptions,
}) {
    const a = new Path2D();
    a.rect(x, y, w, h);
    context.strokeStyle = options.color;
    context.lineWidth = options.size;
    context.stroke(a);
}

export const RectAnnotationHandler: DrawingHandler = {
    onStart: ({x, y, context, options}) => {
        drawRectangle({
            x,
            y,
            w: 0,
            h: 0,
            context,
            options,
        });
    },
    onMove: ({clear, context, startingPoint: {x, y}, deltaY, deltaX, options}) => {
        clear();
        drawRectangle({
            x,
            y,
            w: deltaX,
            h: deltaY,
            context,
            options,
        });
    },
    onEnd: ({onNewAnnotation, startingPoint: {x, y}, deltaY, deltaX, relativeX, relativeY, options}) => {
        const x1 = relativeX(x);
        const y1 = relativeY(y);

        const x2 = relativeX(x + deltaX);
        const y2 = relativeY(y + deltaY);

        const props: Partial<RectangleAnnotation> = {
            type: AnnotationType.Rect,
            c: options.color,
            s: relativeX(options.size),
        };

        if (x1 > x2) {
            props.x1 = x2;
            props.x2 = x1;
        } else {
            props.x1 = x1;
            props.x2 = x2;
        }

        if (y1 > y2) {
            props.y1 = y2;
            props.y2 = y1;
        } else {
            props.y1 = y1;
            props.y2 = y2;
        }

        onNewAnnotation(props as RectangleAnnotation);
    },
    drawAnnotation: ({annotation, context, toX, toY}) => {
        const {x1, y1, x2, y2, c, s} = annotation;
        drawRectangle({
            x: toX(x1),
            y: toY(y1),
            w: toX(x2 - x1),
            h: toY(y2 - y1),
            context,
            options: {color: c, size: toX(s)},
        });
    }
};
