import {AnnotationOptions} from "../annotationTypes.ts";
import {controlsColor, controlsContrastColor, controlsSize, controlsStroke} from "./shapeCommon.ts";
import {DrawContext} from "../events.ts";

export type CircleProps = {
    x: number;
    y: number;
    radius: number;
};

export function getMoveCircleCoordsInCircle({zoom}: DrawContext, {x, y}: CircleProps): CircleProps {
    return {
        x,
        y,
        radius: controlsSize / zoom,
    };
}

export function getResizeCircleCoords({zoom}: DrawContext, {x, y, radius}: CircleProps): CircleProps {
    return {
        x: x + radius,
        y,
        radius: controlsSize / zoom,
    };
}

export function drawCircleControl(
    drawContext: DrawContext,
    {
        x,
        y,
        radius,
    }: CircleProps,
) {
    drawCircle(drawContext, {x, y, radius}, {
        color: controlsContrastColor,
        size: Math.max(.3, controlsStroke / drawContext.zoom),
        fillColor: controlsColor,
    });
}

export function drawCircle(
    drawContext: DrawContext,
    {
        x,
        y,
        radius,
    }: CircleProps,
    options: AnnotationOptions,
    controls: boolean = false
) {
    const a = new Path2D();
    a.arc(x, y, radius, 0, 2 * Math.PI, false);
    const {context} = drawContext;
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.stroke(a);
    if (options.fillColor) {
        context.fillStyle = options.fillColor;
        context.fill(a);
    }

    if (controls) {
        drawCircleControl(
            drawContext,
            getMoveCircleCoordsInCircle(drawContext, {x, y, radius}),
        );
        drawCircleControl(
            drawContext,
            getResizeCircleCoords(drawContext, {x, y, radius}),
        );
    }
}

export function isPointInCircle(
    x: number,
    y: number,
    {x: cx, y: cy, radius}: CircleProps
) {
    return Math.sqrt((x - cx) ** 2 + (y - cy) ** 2) < radius;
}
