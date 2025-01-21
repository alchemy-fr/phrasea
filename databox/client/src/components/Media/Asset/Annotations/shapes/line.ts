import {AnnotationOptions} from "../annotationTypes.ts";
import {DrawContext} from "../events.ts";
import {isPointInRectangle} from "./RectAnnotationHandler.ts";
import {CircleProps, drawCircleControl, getResizeCircleCoords} from "./circle.ts";
import {controlsSize} from "./shapeCommon.ts";

type LineProps = {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
}

export function getLineMoveCircleCoords({zoom}: DrawContext, {x1, y1, x2, y2}: LineProps): CircleProps {
    return {
        x: x1 + (x2 - x1) / 2,
        y: y1 + (y2 - y1) / 2,
        radius: controlsSize / zoom,
    };
}

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
        drawCircleControl(drawContext, getResizeCircleCoords(drawContext, {x: x1, y: y1, radius: 0}));
        drawCircleControl(drawContext, getResizeCircleCoords(drawContext, {x: x2, y: y2, radius: 0}));
        drawCircleControl(drawContext, getLineMoveCircleCoords(drawContext, line));
    }
}

export function isPointInLine(
    x: number,
    y: number,
    {x1, y1, x2, y2}: LineProps
) {
    return isPointInRectangle(x, y, {
        x: Math.min(x1, x2),
        y: Math.min(y1, y2),
        w: Math.abs(x2 - x1),
        h: Math.abs(y2 - y1),
    });
}
