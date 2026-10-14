import {AnnotationOptions, AnnotationType, Point} from '../types';
import type {DrawingHandler} from '../handlerTypes';
import {drawDrawing, getDrawingBoundingBox} from './draw';
import {transformRectangleToPixels} from './rectangle';
import {getStandardMoveHandler} from '../common';

/**
 * Free hand drawing: every stroke adds a path until the tool is left
 * (Escape, right click, another tool), the shape is created then.
 */
export const DrawAnnotationHandler: DrawingHandler = {
    onDrawStart: ({drawContext, x, y, data, options}) => {
        drawDrawing(drawContext, {paths: [[{x, y}]]}, options);

        data.points = [{x, y}];
        data.paths ??= [];
        data.paths.push(data.points);
    },
    onDrawMove: ({drawContext: {context}, x, y, data}) => {
        x = Math.max(0, x);
        y = Math.max(0, y);

        context.lineTo(x, y);
        context.stroke();
        data.points.push({x, y});
    },
    onDrawEnd: ({drawContext, terminate, data}) => {
        drawContext.context.closePath();

        // A simple click ends the drawing
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
        if (data?.paths?.length > 0) {
            onNewAnnotation({
                type: AnnotationType.Draw,
                paths: data.paths.map((points: Point[]) =>
                    points.map((p: Point) => ({
                        x: relativeX(p.x),
                        y: relativeY(p.y),
                    }))
                ),
                c: options.color,
                s: relativeX(options.size),
            });
        }
    },
    drawAnnotation: (
        {annotation: {paths, c, s, x, y}, drawContext, toX, toY},
        {selected, editable}
    ) => {
        drawDrawing(
            drawContext,
            {
                x: toX(x ?? 0),
                y: toY(y ?? 0),
                paths: paths.map((path: Point[]) =>
                    path.map(({x, y}: Point) => ({
                        x: toX(x),
                        y: toY(y),
                    }))
                ),
            },
            {
                color: c,
                size: toX(s),
            },
            selected && editable
        );
    },
    getResizeHandler: () => undefined,
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
    getBoundingBox: ({annotation, toY, toX}) =>
        transformRectangleToPixels(
            getDrawingBoundingBox({
                x: annotation.x,
                y: annotation.y,
                paths: annotation.paths,
            }),
            toX,
            toY
        ),
    getMoveHandler: getStandardMoveHandler,
};
