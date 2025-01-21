import {
    AnnotationOptions,
    AnnotationType,
    DrawAnnotation,
    Point,
} from '../annotationTypes.ts';
import {DrawContext, DrawingHandler} from '../events.ts';

function init(
    drawContext: DrawContext,
    options: AnnotationOptions,
    applyStyle: ApplyStyle | undefined
) {
    const {context} = drawContext;
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.lineJoin = 'round';
    context.lineCap = 'round';
    context.beginPath();
    applyStyle?.(drawContext);
}

type ApplyStyle = (drawContext: DrawContext) => void;

export function createDrawAnnotationHandler(
    annotationType: AnnotationType,
    onPoint: (props: {
        drawContext: DrawContext;
        point: Point;
        index: number;
        options: AnnotationOptions;
    }) => void,
    applyStyle?: ApplyStyle
): DrawingHandler {
    return {
        onDrawStart: ({drawContext, x, y, data, options}) => {
            init(drawContext, options, applyStyle);
            onPoint({
                drawContext,
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
        onDrawMove: ({drawContext, x, y, data, options}) => {
            if (x <= 0) {
                x = 0;
            }
            if (y <= 0) {
                y = 0;
            }
            applyStyle?.(drawContext);
            onPoint({
                drawContext,
                point: {
                    x,
                    y,
                },
                index: 1,
                options,
            });
            data.points.push({x, y});
        },
        onDrawEnd: ({drawContext, terminate, data}) => {
            drawContext.context.closePath();

            if (data.points.length === 1) {
                data.paths.pop();
                terminate();
            }
        },
        onTerminate: ({
            data,
            drawContext,
            onNewAnnotation,
            relativeX,
            relativeY,
            options,
        }) => {
            drawContext.context.closePath();
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
        drawAnnotation: ({annotation: {paths, c, s}, drawContext, toX, toY}) => {
            const options = {
                color: c,
                size: toX(s),
            };
            init(drawContext, options, applyStyle);

            (paths as DrawAnnotation['paths']).forEach(path =>
                path.forEach((point: Point, i) => {
                    onPoint({
                        drawContext,
                        point: {
                            x: toX(point.x),
                            y: toY(point.y),
                        },
                        index: i,
                        options: options,
                    });
                })
            );
            drawContext.context.closePath();
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
    ({drawContext, point, index}) => {
        const {context} = drawContext;
        if (index === 0) {
            context.moveTo(point.x, point.y);
        } else {
            context.lineTo(point.x, point.y);
        }
        context.stroke();
    }
);
