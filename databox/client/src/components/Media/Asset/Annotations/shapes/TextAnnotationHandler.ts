import {AnnotationOptions, AnnotationType, TextAnnotation,} from '../annotationTypes.ts';
import {drawText, getResizeTextCircleCoords, getTextDimensions, getTextSizeFromDist, growFactor} from './text.ts';
import {isPointInCircle} from './circle.ts';
import {getStandardMoveHandler} from '../common.ts';
import {DrawingHandler} from "../events.ts";
import {getDefaultSize, updateLastSize} from "../defaultOptions.ts";

export const TextAnnotationHandler: DrawingHandler = {
    onDrawStart: ({
        x,
        y,
        drawContext,
        options,
        data,
    }) => {
        data.text = 'Text';

        drawText(
            drawContext,
            {
                x,
                y,
                text: data.text,
            },
            {
                ...options,
                size: 0,
            }
        );
    },
    onDrawMove: ({clear, drawContext, data, x, y, startingPoint, options}) => {
        clear();
        const size = getTextSizeFromDist(startingPoint, {
            x, y,
        });

        drawText(
            drawContext,
            {
                x: startingPoint.x,
                y: startingPoint.y,
                text: data.text,
            },
            {
                ...options,
                size: size,
            }
        );
    },
    onDrawEnd: ({terminate, data, onNewAnnotation, startingPoint, x, y, relativeX, relativeY, options}) => {
        const {text} = data;
        let size = getTextSizeFromDist(startingPoint, {x, y});

        if (size <= 0) {
            size = getDefaultSize(AnnotationType.Text);
        } else {
            updateLastSize(AnnotationType.Text, size);
        }

        onNewAnnotation({
            type: AnnotationType.Text,
            text,
            name: text,
            x: relativeX(startingPoint.x),
            y: relativeY(startingPoint.y),
            s: relativeX(size),
            c: options.color,
        } as TextAnnotation);
        terminate();
    },
    drawAnnotation: ({annotation, drawContext, toX, toY}, {selected, editable}) => {
        const {x, y, text, c, s} = annotation;
        drawText(
            drawContext,
            {
                x: toX(x),
                y: toY(y),
                text: text,
            },
            {
                color: c,
                size: toX(s),
            },
            selected && editable
        );
    },
    onTerminate: () => {
    },
    getResizeHandler: ({annotation, drawContext, x, y, toX, toY}) => {
        const pX = toX(annotation.x);
        const pY = toY(annotation.y);
        const pS = toX(annotation.s);
        const resizeTextCircleCoords = getResizeTextCircleCoords(drawContext, {
            x: pX,
            y: pY,
            text: annotation.text,
        }, {
            color: annotation.c,
            size: pS,
        });

        const {width: originWidth, height: originHeight} = getTextDimensions(
            drawContext.context,
            annotation.text,
            pS
        );

        if (
            isPointInCircle(
                x,
                y,
                resizeTextCircleCoords
            )
        ) {
            return ({annotation, relativeX, deltaX, deltaY}) => {
                const size = getTextSizeFromDist({
                    x: pX,
                    y: pY,
                }, {
                    x: pX + originWidth + deltaX,
                    y: pY + originHeight * growFactor + deltaY
                });

                updateLastSize(AnnotationType.Text, size);

                return {
                    ...annotation,
                    s: relativeX(size),
                };
            };
        }
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
    getBoundingBox: ({drawContext, annotation: {x, y, text, s}, toX, toY}) => {
        const {width, height} = getTextDimensions(
            drawContext.context,
            text,
            toX(s)
        );

        return {
            x: toX(x),
            y: toY(y) - height,
            w: width,
            h: height,
        };
    },
    getMoveHandler: getStandardMoveHandler,
    onRename: ({annotation, newName}) => {
        return {
            ...annotation,
            text: newName,
        };
    },
};
