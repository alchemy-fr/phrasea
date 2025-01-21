import {DrawContext} from "../events.ts";
import {AnnotationOptions} from "../annotationTypes.ts";
import {drawRectangle} from "./rectangle.ts";
import {controlsSize} from "./shapeCommon.ts";

export type TextProps = {
    x: number;
    y: number;
    text: string;
}

export function getTextDimensions(context: CanvasRenderingContext2D, text: string, size: number): {
    width: number;
    height: number;
} {
    const sizeCoef = 5;
    context.font = `${size * sizeCoef}px serif`;

    const textMetrics = context.measureText(text);
    return {
        width: textMetrics.width,
        height: size * sizeCoef,
    };
}

export function drawText(
    drawContext: DrawContext,
    {
        x,
        y,
        text,
    }: TextProps,
    options: AnnotationOptions, selected: boolean = false
) {
    const {context} = drawContext;
    const {size} = options;

    context.fillStyle = options.color ?? '#000000';
    const {width, height} = getTextDimensions(context, text, size);
    context.fillText(text, x, y);

    if (selected) {
        drawRectangle(drawContext, {x, y: y - height, w: width, h: height}, {
            color: 'rgba(0,0,0, 0.7)',
            size: Math.max(1 / window.devicePixelRatio, 1 / drawContext.zoom),
        }, selected, false);
    }
}
