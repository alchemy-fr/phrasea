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
    onDrawStart: ({context, x, y, data, options}) => {
        init(context, options);
        context.moveTo(x, y);
        data.points = [{x, y}];
        data.paths ??= [];
        data.paths.push(data.points);
    },
    onDrawMove: ({context, x, y, data}) => {
        if (x <= 0) {
            x = 0;
        }
        if (y <= 0) {
            y = 0;
        }
        context.lineTo(x, y);
        context.stroke();
        data.points.push({x, y});
    },
    onDrawEnd: ({context, terminate, data}) => {
        context.closePath();

        if (data.points.length === 1) {
            data.paths.pop();
            terminate();
        }
    },
    onTerminate: ({data, context, onNewAnnotation, relativeX, relativeY, options}) => {
        context.closePath();
        if (data.paths.length > 0) {
            onNewAnnotation({
                type: AnnotationType.Draw,
                paths: data.paths.map((points: Point[]) => points.map((p: Point) => {
                    return {
                        x: relativeX(p.x),
                        y: relativeY(p.y),
                    };
                })),
                c: options.color,
                s: relativeX(options.size),
            });
        }
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

        (paths as DrawAnnotation['paths']).forEach(path => path.forEach((point: Point, i) => {
            if (i === 0) {
                context.moveTo(toX(point.x), toY(point.y));
            } else {
                context.lineTo(toX(point.x), toY(point.y));
            }
        }));
        context.stroke();
        context.closePath();
    },
};
