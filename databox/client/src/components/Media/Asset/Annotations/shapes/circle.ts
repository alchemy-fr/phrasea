import {AnnotationOptions} from '../annotationTypes.ts';
import {
    controlsColor,
    controlsContrastColor,
    controlsSize,
    controlsStroke,
} from '../controls.ts';
import {DrawContext} from '../events.ts';

export type CircleProps = {
    x: number;
    y: number;
    radius: number;
};

export function getMoveCircleCoordsInCircle(
    {zoom}: DrawContext,
    {x, y}: CircleProps
): CircleProps {
    return {
        x,
        y,
        radius: controlsSize / zoom,
    };
}

export function getResizeCircleCoords(
    {zoom}: DrawContext,
    {x, y, radius}: CircleProps
): CircleProps {
    return {
        x: x + radius,
        y,
        radius: controlsSize / zoom,
    };
}

export function drawCircleControl(
    drawContext: DrawContext,
    {x, y}: CircleProps
) {
    drawCircle(
        drawContext,
        {x, y, radius: controlsSize / drawContext.zoom},
        {
            color: controlsContrastColor,
            size: Math.max(0.3, controlsStroke / drawContext.zoom),
            fillColor: controlsColor,
        }
    );
}

export function drawCircle(
    drawContext: DrawContext,
    {x, y, radius}: CircleProps,
    options: AnnotationOptions,
    controls: boolean = false
) {
    const a = new Path2D();
    a.arc(x, y, radius, 0, 2 * Math.PI, false);
    const {context} = drawContext;
    context.lineWidth = options.size;
    context.strokeStyle = options.color ?? '#000';
    context.stroke(a);
    if (options.fillColor) {
        context.fillStyle = options.fillColor;
        context.fill(a);
    }

    if (controls) {
        drawCircleControl(
            drawContext,
            getResizeCircleCoords(drawContext, {x, y, radius})
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
