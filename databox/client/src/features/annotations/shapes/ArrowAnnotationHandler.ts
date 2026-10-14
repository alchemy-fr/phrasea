import type {DrawingHandler} from '../handlerTypes';
import {createLineAnnotationHandler} from './LineAnnotationHandler';
import {drawLine as baseDrawLine} from './line';
import {AnnotationType} from '../types';

const drawArrow: typeof baseDrawLine = (
    drawContext,
    line,
    options,
    selected = false
) => {
    const {x1, y1, x2, y2} = line;
    const openingAngle = Math.PI / 6;
    const arrowSize = 7 * options.size;

    const angle = Math.atan2(y2 - y1, x2 - x1);

    for (const side of [-1, 1]) {
        baseDrawLine(
            drawContext,
            {
                x1: x2 - arrowSize * Math.cos(angle + side * openingAngle),
                y1: y2 - arrowSize * Math.sin(angle + side * openingAngle),
                x2,
                y2,
            },
            options
        );
    }

    baseDrawLine(drawContext, line, options, selected);
};

export const ArrowAnnotationHandler: DrawingHandler =
    createLineAnnotationHandler(drawArrow, AnnotationType.Arrow);
