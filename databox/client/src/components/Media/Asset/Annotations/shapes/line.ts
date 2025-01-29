import {AnnotationOptions} from '../annotationTypes.ts';
import {DrawContext} from '../events.ts';
import {drawCircleControl, getResizeCircleCoords} from './circle.ts';

type LineProps = {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
};

export function drawLine(
    drawContext: DrawContext,
    line: LineProps,
    options: AnnotationOptions,
    controls: boolean = false
) {
    const {x1, y1, x2, y2} = line;
    const {context} = drawContext;
    context.strokeStyle = options.color ?? '#000';
    context.lineWidth = options.size;
    context.beginPath();
    context.moveTo(x1, y1);
    context.lineTo(x2, y2);
    context.stroke();

    if (controls) {
        drawCircleControl(
            drawContext,
            getResizeCircleCoords(drawContext, {x: x1, y: y1, radius: 0})
        );
        drawCircleControl(
            drawContext,
            getResizeCircleCoords(drawContext, {x: x2, y: y2, radius: 0})
        );
    }
}
