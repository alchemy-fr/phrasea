import {AnnotationOptions} from "../annotationTypes.ts";
import {CircleProps, drawCircleControl} from "./circle.ts";
import {controlsSize} from "./shapeCommon.ts";
import {DrawContext, ToFunction} from "../events.ts";

export type RectangleProps = {
    x: number;
    y: number;
    w: number;
    h: number;
}


export function getMoveCircleCoordsInRectangle({zoom}: DrawContext, {x, y, w, h}: RectangleProps): CircleProps {
    return {
        x: x + w / 2,
        y: y + h / 2,
        radius: controlsSize / zoom,
    };
}


export function drawRectangle(
    drawContext: DrawContext,
    {
        x,
        y,
        w,
        h,
    }: RectangleProps,
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

    if (selected) {
        drawCircleControl(drawContext, {
            x: x + w / 2,
            y: y + h / 2,
            radius: controlsSize / drawContext.zoom
        });

        if (resize) {
            [0, 1].forEach((i) => {
                [0, 1].forEach((j) => {
                    drawCircleControl(drawContext, {
                        x: x + w * i,
                        y: y + h * j,
                        radius: controlsSize / drawContext.zoom
                    });
                });
            });
        }
    }
}

export function normalizeRectangleProps({x, y, w, h}: RectangleProps): RectangleProps {
    const props: Partial<RectangleProps> = {};

    if (w >= 0) {
        props.x = x;
        props.w = w;
    } else {
        props.x = x + w;
        props.w = -w;
    }

    if (h >= 0) {
        props.y = y;
        props.h = h;
    } else {
        props.y = y + h;
        props.h = -h;
    }

    return props as RectangleProps;
}

export function transformRectangleToPixels(
    rect: RectangleProps,
    toX: ToFunction,
    toY: ToFunction
) {
    return {
        x: toX(rect.x),
        y: toY(rect.y),
        w: toX(rect.w),
        h: toY(rect.h),
    }
}
