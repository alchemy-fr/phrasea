import {AnnotationOptions, AnnotationType, TextAnnotation,} from '../annotationTypes.ts';
import {DrawingHandler} from '../events.ts';
import {drawText, getTextDimensions} from "./text.ts";
import {isPointInRectangle} from "./RectAnnotationHandler.ts";
import {isPointInCircle} from "./circle.ts";
import {getMoveCircleCoordsInRectangle} from "./rectangle.ts";

export const TextAnnotationHandler: DrawingHandler = {
    onDrawStart: ({x, y, drawContext, options, onNewAnnotation, relativeY, relativeX, terminate}) => {
        const text = 'Hello World!';

        drawText(
            drawContext,
            {
                x,
                y,
                text,
            },
            options,
        );
        onNewAnnotation({
            type: AnnotationType.Text,
            text,
            x: relativeX(x),
            y: relativeY(y),
            s: relativeX(options.size),
            c: options.color,
        } as TextAnnotation);
        terminate();
    },
    onDrawMove: () => {
    },
    onDrawEnd: () => {
    },
    drawAnnotation: ({annotation, drawContext, toX, toY}, selected) => {
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
                size: toX(s)
            }
            , selected);
    },
    onTerminate: () => {
    },
    isPointInside: ({drawContext: {context}, annotation, x, y, toX, toY}) => {
        const {width, height} = getTextDimensions(context, annotation.text, toX(annotation.s));

        return isPointInRectangle(x, y, {
            x: toX(annotation.x),
            y: toY(annotation.y) - height,
            w: width,
            h: height,
        });
    },
    getResizeHandler: ({annotation, drawContext, x, y, toX, toY}) => {
        const {width, height} = getTextDimensions(drawContext.context, annotation.text, toX(annotation.s));

        if (isPointInCircle(x, y, getMoveCircleCoordsInRectangle(drawContext, {
            x: toX(annotation.x),
            y: toY(annotation.y) - height,
            w: width,
            h: height,
        }))) {
            return ({annotation, relativeX, relativeY, deltaX, deltaY}) => {
                return {
                    ...annotation,
                    x: annotation.x + relativeX(deltaX),
                    y: annotation.y + relativeY(deltaY),
                };
            };
        }
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
};
