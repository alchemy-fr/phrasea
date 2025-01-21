import {DrawingHandler} from '../events.ts';
import {createLineAnnotationHandler} from './LineAnnotationHandler.ts';
import {drawLine as baseDrawLine} from './line.ts';
import {AnnotationType} from '../annotationTypes.ts';

const drawArrow: typeof baseDrawLine = (
    drawContext,
    line,
    options,
    selected = false
) => {
    const {x1, y1, x2, y2} = line;
    const openingAngle = Math.PI / 6;
    const arrowSize = 7 * options.size;

    const dx = x2 - x1;
    const dy = y2 - y1;
    const angle = Math.atan2(dy, dx);

    baseDrawLine(
        drawContext,
        {
            x1: x2 - arrowSize * Math.cos(angle - openingAngle),
            y1: y2 - arrowSize * Math.sin(angle - openingAngle),
            x2: x2,
            y2: y2,
        },
        options
    );

    baseDrawLine(
        drawContext,
        {
            x1: x2 - arrowSize * Math.cos(angle + openingAngle),
            y1: y2 - arrowSize * Math.sin(angle + openingAngle),
            x2: x2,
            y2: y2,
        },
        options
    );

    baseDrawLine(drawContext, line, options, selected);
};

export const ArrowAnnotationHandler: DrawingHandler =
    createLineAnnotationHandler(drawArrow, AnnotationType.Arrow);
