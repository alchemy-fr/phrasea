import {AnnotationOptions, AnnotationType, TextAnnotation} from '../types';
import {
    drawText,
    getResizeTextCircleCoords,
    getTextDimensions,
    getTextSizeFromDist,
    growFactor,
} from './text';
import {isPointInCircle} from './circle';
import {getStandardMoveHandler} from '../common';
import type {DrawingHandler} from '../handlerTypes';
import {getDefaultSize, updateLastSize} from '../defaultOptions';

/** The placeholder text, renamed through the shape control */
const defaultText = 'Text';

export const TextAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options, data}) => {
        data.text = defaultText;

        drawText(
            drawContext,
            {x, y, text: data.text},
            {
                ...options,
                size: 0,
            }
        );
    },
    onDrawMove: ({clear, drawContext, data, x, y, startingPoint, options}) => {
        clear();
        drawText(
            drawContext,
            {
                x: startingPoint.x,
                y: startingPoint.y,
                text: data.text,
            },
            {
                ...options,
                size: getTextSizeFromDist(startingPoint, {x, y}),
            }
        );
    },
    onDrawEnd: ({
        terminate,
        data,
        onNewAnnotation,
        startingPoint,
        x,
        y,
        relativeX,
        relativeY,
        options,
    }) => {
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
    drawAnnotation: (
        {annotation, drawContext, toX, toY},
        {selected, editable}
    ) => {
        const {x, y, text, c, s} = annotation;
        drawText(
            drawContext,
            {x: toX(x), y: toY(y), text},
            {color: c, size: toX(s)},
            selected && editable
        );
    },
    onTerminate: () => {},
    getResizeHandler: ({annotation, drawContext, x, y, toX, toY}) => {
        const pX = toX(annotation.x);
        const pY = toY(annotation.y);
        const pS = toX(annotation.s);
        const resizeTextCircleCoords = getResizeTextCircleCoords(
            drawContext,
            {x: pX, y: pY, text: annotation.text},
            {color: annotation.c, size: pS}
        );

        const {width: originWidth, height: originHeight} = getTextDimensions(
            drawContext.context,
            annotation.text,
            pS
        );

        if (isPointInCircle(x, y, resizeTextCircleCoords)) {
            return ({annotation, relativeX, deltaX, deltaY}) => {
                const size = getTextSizeFromDist(
                    {x: pX, y: pY},
                    {
                        x: pX + originWidth + deltaX,
                        y: pY + originHeight * growFactor + deltaY,
                    }
                );

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
    // The name of a text shape is its text
    onRename: ({annotation, newName}) => ({
        ...annotation,
        text: newName,
    }),
};
