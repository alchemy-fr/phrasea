import {AnnotationType} from './annotationTypes.ts';
import {createDrawAnnotationHandler} from './DrawAnnotationHandler.ts';

export const HighlightAnnotationHandler = createDrawAnnotationHandler(
    AnnotationType.Highlight,
    ({context, point, options: {size}}) => {
        context.fillStyle = '#ff0';
        context.fillRect(point.x - size / 2, point.y - size / 2, size, size);
    },
    context => {
        context.globalCompositeOperation = 'multiply';
        context.globalAlpha = 0.2;
    }
);
