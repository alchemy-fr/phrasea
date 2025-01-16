import {
    AnnotationOptions,
    AnnotationType,
    DrawAnnotation,
    Point,
} from '../annotationTypes.ts';
import {DrawingHandler} from '../events.ts';

function init(
    context: CanvasRenderingContext2D,
    options: AnnotationOptions,
    applyStyle: ApplyStyle | undefined
) {
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.lineJoin = 'round';
    context.lineCap = 'round';
    context.beginPath();
    applyStyle?.(context);
}

type ApplyStyle = (context: CanvasRenderingContext2D) => void;

export function createDrawAnnotationHandler(
    annotationType: AnnotationType,
    onPoint: (props: {
        context: CanvasRenderingContext2D;
        point: Point;
        index: number;
        options: AnnotationOptions;
    }) => void,
    applyStyle?: ApplyStyle
): DrawingHandler {
    return {
        onDrawStart: ({context, x, y, data, options}) => {
            init(context, options, applyStyle);
            onPoint({
                context,
                point: {
                    x,
                    y,
                },
                index: 0,
                options,
            });
            data.points = [{x, y}];
            data.paths ??= [];
            data.paths.push(data.points);
        },
        onDrawMove: ({context, x, y, data, options}) => {
            if (x <= 0) {
                x = 0;
            }
            if (y <= 0) {
                y = 0;
            }
            applyStyle?.(context);
            onPoint({
                context,
                point: {
                    x,
                    y,
                },
                index: 1,
                options,
            });
            data.points.push({x, y});
        },
        onDrawEnd: ({context, terminate, data}) => {
            context.closePath();

            if (data.points.length === 1) {
                data.paths.pop();
                terminate();
            }
        },
        onTerminate: ({
            data,
            context,
            onNewAnnotation,
            relativeX,
            relativeY,
            options,
        }) => {
            context.closePath();
            if (data.paths.length > 0) {
                onNewAnnotation({
                    type: annotationType,
                    paths: data.paths.map((points: Point[]) =>
                        points.map((p: Point) => {
                            return {
                                x: relativeX(p.x),
                                y: relativeY(p.y),
                            };
                        })
                    ),
                    c: options.color,
                    s: relativeX(options.size),
                });
            }
        },
        drawAnnotation: ({annotation: {paths, c, s}, context, toX, toY}) => {
            const options = {
                color: c,
                size: toX(s),
            };
            init(context, options, applyStyle);

            (paths as DrawAnnotation['paths']).forEach(path =>
                path.forEach((point: Point, i) => {
                    onPoint({
                        context,
                        point: {
                            x: toX(point.x),
                            y: toY(point.y),
                        },
                        index: i,
                        options: options,
                    });
                })
            );
            context.closePath();
        },
        isPointInside: ({}) => false,
        getResizeHandler: () => undefined,
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
}

export const DrawAnnotationHandler = createDrawAnnotationHandler(
    AnnotationType.Draw,
    ({context, point, index}) => {
        if (index === 0) {
            context.moveTo(point.x, point.y);
        } else {
            context.lineTo(point.x, point.y);
        }
        context.stroke();
    }
);
