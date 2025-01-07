import {AnnotationOptions, AnnotationType, OnNewAnnotation, Point, RectangleAnnotation} from "./annotationTypes.ts";

export type StartingPoint = Point;

type Clear = () => void;

type BaseEvent = {
    canvas: HTMLCanvasElement;
    context: CanvasRenderingContext2D;
    startingPoint: StartingPoint;
    data: any;
    options: AnnotationOptions;
} & Point;

type OnStartDrawingEvent = {} & BaseEvent;

type OnDrawMoveEvent = {
    deltaX: number;
    deltaY: number;
    clear: Clear;
} & BaseEvent;

type OnEndDrawingEvent = {
    deltaX: number;
    deltaY: number;
    onNewAnnotation: OnNewAnnotation;
    relativeX: (x: number) => number;
    relativeY: (y: number) => number;
} & BaseEvent;

type OnStartDrawing = (event: OnStartDrawingEvent) => void;
type OnDrawMove = (event: OnDrawMoveEvent) => void;
type OnEndDrawing = (event: OnEndDrawingEvent) => void;

export type DrawingHandler = {
    onStart: OnStartDrawing;
    onMove: OnDrawMove;
    onEnd: OnEndDrawing;
}

function drawCircle({
    x,
    y,
    context,
    radius,
    options,
}: {
    x: number;
    y: number;
    context: CanvasRenderingContext2D;
    radius: number;
    options: AnnotationOptions,
}) {
    const a = new Path2D();
    a.arc(x, y, radius, 0, 2 * Math.PI, false);
    context.lineWidth = options.size;
    context.strokeStyle = options.color;
    context.stroke(a);
}

function drawPoint({
    x,
    y,
    context,
    options,
}: {
    x: number;
    y: number;
    context: CanvasRenderingContext2D;
    options: AnnotationOptions,
}) {
    const a = new Path2D();
    a.arc(x, y, options.size, 0, 2 * Math.PI, false);
    context.fillStyle = options.color;
    context.fill(a);
}

function drawRectangle({
    x,
    y,
    w,
    h,
    context,
    options,
}: {
    x: number;
    y: number;
    w: number;
    h: number;
    context: CanvasRenderingContext2D;
    options: AnnotationOptions,
}) {
    const a = new Path2D();
    a.rect(x, y, w, h);
    context.strokeStyle = options.color;
    context.lineWidth = options.size;
    context.stroke(a);
}

function getRadius(deltaX: number, deltaY: number) {
    return Math.abs(3 + Math.max(Math.abs(deltaX), Math.abs(deltaY)) * (deltaX < 0 || deltaY < 0 ? -1 : 1));
}

export const drawingHandlers: Record<AnnotationType, DrawingHandler> = {
    [AnnotationType.Circle]: {
        onStart: ({x, y, context, options}) => {
            drawCircle({
                x,
                y,
                context,
                radius: 3,
                options,
            });
        },
        onMove: ({clear, startingPoint: {x, y}, context, deltaX, deltaY, options}) => {
            clear();
            const radius = getRadius(deltaX, deltaY);
            drawCircle({
                x,
                y,
                context,
                radius,
                options,
            });
        },
        onEnd: ({onNewAnnotation, startingPoint: {x, y}, deltaX, deltaY, relativeX, relativeY, options}) => {
            onNewAnnotation({
                type: AnnotationType.Circle,
                x: relativeX(x),
                y: relativeY(y),
                r: relativeX(getRadius(deltaX, deltaY)),
                c: options.color,
                s: options.size,
            });
        },
    },
    [AnnotationType.Point]: {
        onStart: ({x, y, context, options}) => {
            drawPoint({
                x,
                y,
                context,
                options,
            });
        },
        onMove: ({clear, context, x, y, options}) => {
            clear();
            drawPoint({
                x,
                y,
                context,
                options,
            });
        },
        onEnd: ({onNewAnnotation, x, y, relativeX, relativeY, options}) => {
            onNewAnnotation({
                type: AnnotationType.Point,
                x: relativeX(x),
                y: relativeY(y),
                c: options.color,
                s: options.size,
            });
        },
    },
    [AnnotationType.Rect]: {
        onStart: ({x, y, context, options}) => {
            drawRectangle({
                x,
                y,
                w: 0,
                h: 0,
                context,
                options,
            });
        },
        onMove: ({clear, context, startingPoint: {x, y}, deltaY, deltaX, options}) => {
            clear();
            drawRectangle({
                x,
                y,
                w: deltaX,
                h: deltaY,
                context,
                options,
            });
        },
        onEnd: ({onNewAnnotation, startingPoint: {x, y}, deltaY, deltaX, relativeX, relativeY, options}) => {
            const x1 = relativeX(x);
            const y1 = relativeY(y);

            const x2 = relativeX(x + deltaX);
            const y2 = relativeY(y + deltaY);

            const props: Partial<RectangleAnnotation> = {
                type: AnnotationType.Rect,
                c: options.color,
                s: options.size,
            };

            if (x1 > x2) {
                props.x1 = x2;
                props.x2 = x1;
            } else {
                props.x1 = x1;
                props.x2 = x2;
            }

            if (y1 > y2) {
                props.y1 = y2;
                props.y2 = y1;
            } else {
                props.y1 = y1;
                props.y2 = y2;
            }

            onNewAnnotation(props as RectangleAnnotation);
        },
    },
    [AnnotationType.Draw]: {
        onStart: ({context, x, y, data, options}) => {
            context.lineWidth = options.size;
            context.lineJoin = "round";
            context.lineCap = "round";
            context.strokeStyle = options.color;
            context.beginPath();
            context.moveTo(x, y);
            data.paths = [{x, y}];
        },
        onMove: ({context, x, y, data}) => {
            if (x <= 0) {
                x = 0;
            }
            if (y <= 0) {
                y = 0;
            }
            context.lineTo(x, y);
            context.stroke();
            data.paths.push({x, y});
        },
        onEnd: ({context, onNewAnnotation, data, relativeY, relativeX, options}) => {
            context.closePath();
            onNewAnnotation({
                type: AnnotationType.Draw,
                paths: data.paths.map((p: Point) => {
                    return {
                        x: relativeX(p.x),
                        y: relativeY(p.y),
                    };
                }),
                c: options.color,
                s: options.size,
            });
        },
    },
} as Record<AnnotationType, DrawingHandler>;
