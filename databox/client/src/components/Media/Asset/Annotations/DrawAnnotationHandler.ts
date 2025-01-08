import {AnnotationOptions, AnnotationType, DrawAnnotation, Point} from "./annotationTypes.ts";
import {DrawingHandler} from "./events.ts";

function init(context: CanvasRenderingContext2D, options: AnnotationOptions) {
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.lineJoin = "round";
    context.lineCap = "round";
    context.beginPath();
}

export const DrawAnnotationHandler: DrawingHandler = {
    onStart: ({context, x, y, data, options}) => {
        init(context, options);
        context.moveTo(x, y);
        data.paths = [{x, y}];
    },
    onMove: ({context, x, y, data}) => {
        if (x <= 0) {
            x = 0;
        }
        if (y <= 0) {
            y = 0;
        }
        context.lineTo(x, y);
        context.stroke();
        data.paths.push({x, y});
    },
    onEnd: ({context, onNewAnnotation, data, relativeY, relativeX, options}) => {
        context.closePath();
        onNewAnnotation({
            type: AnnotationType.Draw,
            paths: data.paths.map((p: Point) => {
                return {
                    x: relativeX(p.x),
                    y: relativeY(p.y),
                };
            }),
            c: options.color,
            s: relativeX(options.size),
        });
    },
    drawAnnotation: ({annotation: {
        paths,
        c,
        s,
    }, context, toX, toY}) => {
        init(context, {
            color: c,
            size: toX(s),
        });

        (paths as DrawAnnotation['paths']).forEach((point: Point, i) => {
            if (i === 0) {
                context.moveTo(toX(point.x), toY(point.y));
            } else {
                context.lineTo(toX(point.x), toY(point.y));
            }
        });
        context.stroke();
        context.closePath();
    }
};
