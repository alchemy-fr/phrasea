import {AnnotationOptions} from "../annotationTypes.ts";
import {drawCircleControl} from "./circle.ts";
import {controlsSize} from "./shapeCommon.ts";

export type RectangleProps = {
    x: number;
    y: number;
    w: number;
    h: number;
}

export function drawRectangle(
    context: CanvasRenderingContext2D,
    {
        x,
        y,
        w,
        h,
    }: RectangleProps,
    options: AnnotationOptions, selected: boolean = false
) {
    const a = new Path2D();
    a.rect(x, y, w, h);
    context.strokeStyle = options.color;
    context.lineWidth = options.size;
    context.stroke(a);

    if (selected) {
        drawCircleControl(context, {
            x: x + w / 2,
            y: y + h / 2,
            radius: controlsSize
        });

        [0, 1].forEach((i) => {
            [0, 1].forEach((j) => {
                drawCircleControl(context, {
                    x: x + w * i,
                    y: y + h * j,
                    radius: controlsSize
                });
            });
        });
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
