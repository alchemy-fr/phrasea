import {DrawContext} from "../events.ts";
import {AnnotationOptions, Point} from "../annotationTypes.ts";
import {getMoveCircleCoordsInRectangle} from "./rectangle.ts";
import {drawCircleControl} from "./circle.ts";

type Drawing = {
    paths: Point[][];
    x?: number;
    y?: number;
}

function init(
    drawContext: DrawContext,
    options: AnnotationOptions,
) {
    const {context} = drawContext;
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.lineJoin = 'round';
    context.lineCap = 'round';
    context.beginPath();
}

export function drawDrawing(
    drawContext: DrawContext,
    drawing: Drawing,
    options: AnnotationOptions,
    selected: boolean = false,
) {
    init(drawContext, options);
    const {context} = drawContext;

    const offsetX = drawing.x ?? 0;
    const offsetY = drawing.y ?? 0;

    drawing.paths.forEach((path) => {
        path.forEach((point, index) => {
            if (index === 0) {
                context.moveTo(offsetX + point.x, offsetY + point.y);
            } else {
                context.lineTo(offsetX + point.x, offsetY + point.y);
            }
            context.stroke();
        });
    });
    drawContext.context.closePath();

    if (selected) {
        drawCircleControl(
            drawContext,
            getMoveCircleCoordsInRectangle(drawContext, getDrawingBoundingBox(drawing))
        );
    }
}


export function getDrawingBoundingBox({x: offsetX, y: offsetY, paths}: Drawing) {
    const xs = paths.map(path => path.map(p => p.x));
    const ys = paths.map(path => path.map(p => p.y));

    const x = Math.min(...xs.flat());
    const y = Math.min(...ys.flat());
    const w = Math.max(...xs.flat()) - x;
    const h = Math.max(...ys.flat()) - y;

    return {
        x: x + (offsetX ?? 0),
        y: y + (offsetY ?? 0),
        w,
        h,
    };
}
