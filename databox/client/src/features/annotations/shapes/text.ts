import type {DrawContext} from '../handlerTypes';
import type {AnnotationOptions, Point} from '../types';
import {CircleProps, drawCircleControl} from './circle';
import {controlsSize} from '../controls';

export type TextProps = {
    x: number;
    y: number;
    text: string;
};

export function getTextDimensions(
    context: CanvasRenderingContext2D,
    text: string,
    size: number
): {
    width: number;
    height: number;
} {
    context.font = `${size}px sans-serif`;

    const textMetrics = context.measureText(text);

    return {
        width: textMetrics.width,
        height: size,
    };
}

export function drawText(
    drawContext: DrawContext,
    textProps: TextProps,
    options: AnnotationOptions,
    selected: boolean = false
) {
    const {x, y, text} = textProps;
    const {context} = drawContext;
    const {size} = options;

    context.fillStyle = options.color ?? '#000';
    getTextDimensions(context, text, size);
    context.fillText(text, x, y);

    if (selected) {
        drawCircleControl(
            drawContext,
            getResizeTextCircleCoords(drawContext, textProps, options)
        );
    }
}

export function getResizeTextCircleCoords(
    {context, zoom}: DrawContext,
    {x, y, text}: TextProps,
    {size}: AnnotationOptions
): CircleProps {
    const {width} = getTextDimensions(context, text, size);

    return {
        x: x + width,
        y: y,
        radius: controlsSize / zoom,
    };
}

export const growFactor = 0.4;

/** The font size grows with the distance dragged from the anchor */
export function getTextSizeFromDist(
    {x, y}: Point,
    {x: x2, y: y2}: Point
): number {
    return Math.sqrt((x2 - x) ** 2 + (y2 - y) ** 2) * growFactor;
}
