import type {DrawContext} from '../handlerTypes';
import type {AnnotationOptions, Point} from '../types';
import {getMoveCircleCoordsInRectangle, RectangleProps} from './rectangle';
import {drawCircleControl} from './circle';

export type Drawing = {
    paths: Point[][];
    x?: number;
    y?: number;
};

function init(drawContext: DrawContext, options: AnnotationOptions) {
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
    selected: boolean = false
) {
    init(drawContext, options);
    const {context} = drawContext;

    const offsetX = drawing.x ?? 0;
    const offsetY = drawing.y ?? 0;

    drawing.paths.forEach(path => {
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
            getMoveCircleCoordsInRectangle(
                drawContext,
                getDrawingBoundingBox(drawing)
            )
        );
    }
}

export function getDrawingBoundingBox({
    x: offsetX,
    y: offsetY,
    paths,
}: Drawing): RectangleProps {
    const xs = paths.flat().map(p => p.x);
    const ys = paths.flat().map(p => p.y);

    const x = Math.min(...xs);
    const y = Math.min(...ys);

    return {
        x: x + (offsetX ?? 0),
        y: y + (offsetY ?? 0),
        w: Math.max(...xs) - x,
        h: Math.max(...ys) - y,
    };
}
