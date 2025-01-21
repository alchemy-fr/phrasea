import {AnnotationOptions, AnnotationType, Point,} from '../annotationTypes.ts';
import {DrawingHandler, GetBoundingBoxProps} from '../events.ts';
import {drawDrawing, getDrawingBoundingBox} from "./draw.ts";
import {transformRectangleToPixels} from "./rectangle.ts";
import {getStandardMoveHandler} from "../common.ts";


export function createDrawAnnotationHandler(
    annotationType: AnnotationType,
): DrawingHandler {
    return {
        onDrawStart: ({drawContext, x, y, data, options}) => {
            drawDrawing(drawContext, {
                paths: [
                    [{x, y}]
                ]
            }, options);

            data.points = [{x, y}];
            data.paths ??= [];
            data.paths.push(data.points);
        },
        onDrawMove: ({drawContext: {context}, x, y, data}) => {
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
        drawAnnotation: ({annotation: {paths, c, s, x, y}, drawContext, toX, toY}, selected) => {
            const options = {
                color: c,
                size: toX(s),
            };

            drawDrawing(drawContext, {
                x: toX(x ?? 0),
                y: toY(y ?? 0),
                paths: paths.map((path: Point[]) =>
                    path.map(({x, y}: Point) => ({
                        x: toX(x),
                        y: toY(y),
                    }))
                )
            }, options, selected);
        },
        getResizeHandler: () => {
            return undefined;
        },
        toOptions: ({c, s}, {toX}) => ({
            color: c,
            size: toX(s),
        } as AnnotationOptions),
        fromOptions: (options, annotation, {relativeX}) => ({
            ...annotation,
            c: options.color,
            s: relativeX(options.size),
        }),
        getBoundingBox: ({annotation, toY, toX}: GetBoundingBoxProps) => {
            const box = getDrawingBoundingBox({
                x: annotation.x,
                y: annotation.y,
                paths: annotation.paths
            });

            return transformRectangleToPixels(box, toX, toY);
        },
        getMoveHandler: getStandardMoveHandler,
    };
}


export const DrawAnnotationHandler = createDrawAnnotationHandler(
    AnnotationType.Draw
);
