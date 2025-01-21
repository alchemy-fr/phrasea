import {AnnotationOptions} from "../annotationTypes.ts";

type LineProps = {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
}

export function drawLine(context: CanvasRenderingContext2D, {x1, y1, x2, y2}: LineProps, options: AnnotationOptions) {
    context.strokeStyle = options.color;
    context.lineWidth = options.size;
    context.beginPath();
    context.moveTo(x1, y1);
    context.lineTo(x2, y2);
    context.stroke();
}
