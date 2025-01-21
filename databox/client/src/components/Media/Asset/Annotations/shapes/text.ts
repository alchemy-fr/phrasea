import {DrawContext} from "../events.ts";
import {AnnotationOptions} from "../annotationTypes.ts";
import {getMoveCircleCoordsInRectangle} from "./rectangle.ts";
import {drawCircleControl} from "./circle.ts";

export type TextProps = {
    x: number;
    y: number;
    text: string;
}

export function getTextDimensions(context: CanvasRenderingContext2D, text: string, size: number): {
    width: number;
    height: number;
} {
    const sizeCoef = 3;
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

    context.fillStyle = options.color ?? '#000';
    const {width, height} = getTextDimensions(context, text, size);
    context.fillText(text, x, y);

    if (selected) {
        drawCircleControl(drawContext, getMoveCircleCoordsInRectangle(drawContext, {
            x,
            y: y - height,
            w: width,
            h: height,
        }));
    }
}
