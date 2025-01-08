import {
    AnnotationOptions,
    AnnotationType,
    AssetAnnotation,
    OnNewAnnotation,
    Point,
} from "./annotationTypes.ts";
import {DrawAnnotationHandler} from "./DrawAnnotationHandler.ts";
import {RectAnnotationHandler} from "./RectAnnotationHandler.ts";
import {PointAnnotationHandler} from "./PointAnnotationHandler.ts";
import {CircleAnnotationHandler} from "./CircleAnnotationHandler.ts";

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

type DrawAnnotationProps = {
    annotation: AssetAnnotation;
    context: CanvasRenderingContext2D;
    toX: (relativeX: number) => number;
    toY: (relativeY: number) => number;
}

export type DrawingHandler = {
    onStart: OnStartDrawing;
    onMove: OnDrawMove;
    onEnd: OnEndDrawing;
    drawAnnotation: (props: DrawAnnotationProps) => void;
}

export const drawingHandlers: Record<AnnotationType, DrawingHandler> = {
    [AnnotationType.Circle]: CircleAnnotationHandler,
    [AnnotationType.Point]: PointAnnotationHandler,
    [AnnotationType.Rect]: RectAnnotationHandler,
    [AnnotationType.Draw]: DrawAnnotationHandler,
} as Record<AnnotationType, DrawingHandler>;
