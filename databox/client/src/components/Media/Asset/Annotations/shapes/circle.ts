import {AnnotationOptions} from "../annotationTypes.ts";
import {controlsColor, controlsContrastColor, controlsSize, controlsStroke} from "./shapeCommon.ts";

type CircleProps = {
    x: number;
    y: number;
    radius: number;
};

export function getMoveCircleCoords({x, y}: CircleProps): CircleProps {
    return {
        x,
        y,
        radius: controlsSize,
    };
}

export function getResizeCircleCoords({x, y, radius}: CircleProps): CircleProps {
    return {
        x: x + radius,
        y,
        radius: controlsSize,
    };
}

export function drawCircleControl(
    context: CanvasRenderingContext2D,
    {
        x,
        y,
        radius,
    }: CircleProps,
) {
    drawCircle(context, {x, y, radius}, {
        color: controlsContrastColor,
        size: controlsStroke,
        fillColor: controlsColor,
    });
}

export function drawCircle(
    context: CanvasRenderingContext2D,
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
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.stroke(a);
    if (options.fillColor) {
        context.fillStyle = options.fillColor;
        context.fill(a);
    }

    if (controls) {
        drawCircleControl(
            context,
            getMoveCircleCoords({x, y, radius}),
        );
        drawCircleControl(
            context,
            {
                ...getResizeCircleCoords({x, y, radius}),
                radius: controlsSize
            },
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
