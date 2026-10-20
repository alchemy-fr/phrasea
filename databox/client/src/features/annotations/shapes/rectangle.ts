import type {AnnotationOptions} from '../types';
import {CircleProps, drawCircleControl} from './circle';
import {controlsSize} from '../controls';
import type {DrawContext, ToFunction} from '../handlerTypes';

export type RectangleProps = {
    x: number;
    y: number;
    w: number;
    h: number;
};

export function getMoveCircleCoordsInRectangle(
    {zoom}: DrawContext,
    {x, y, w, h}: RectangleProps
): CircleProps {
    return {
        x: x + w / 2,
        y: y + h / 2,
        radius: controlsSize / zoom,
    };
}

export function drawRectangle(
    drawContext: DrawContext,
    {x, y, w, h}: RectangleProps,
    options: AnnotationOptions,
    selected: boolean = false,
    resize: boolean = true
) {
    const a = new Path2D();
    a.rect(x, y, w, h);
    const {context} = drawContext;
    context.strokeStyle = options.color ?? '#000';
    context.lineWidth = options.size;
    context.stroke(a);
    if (options.fillColor) {
        context.fillStyle = options.fillColor;
        context.fill(a);
    }

    if (selected && resize) {
        [0, 1].forEach(i => {
            [0, 1].forEach(j => {
                drawCircleControl(drawContext, {
                    x: x + w * i,
                    y: y + h * j,
                    radius: 0,
                });
            });
        });
    }
}

export function isPointInRectangle(
    x: number,
    y: number,
    {x: rx, y: ry, w, h}: RectangleProps
) {
    return x >= rx && x <= rx + w && y >= ry && y <= ry + h;
}

/** A rectangle drawn backwards (negative width or height) put straight */
export function normalizeRectangleProps({
    x,
    y,
    w,
    h,
}: RectangleProps): RectangleProps {
    return {
        x: w >= 0 ? x : x + w,
        w: Math.abs(w),
        y: h >= 0 ? y : y + h,
        h: Math.abs(h),
    };
}

export function transformRectangleToPixels(
    rect: RectangleProps,
    toX: ToFunction,
    toY: ToFunction
): RectangleProps {
    return {
        x: toX(rect.x),
        y: toY(rect.y),
        w: toX(rect.w),
        h: toY(rect.h),
    };
}
