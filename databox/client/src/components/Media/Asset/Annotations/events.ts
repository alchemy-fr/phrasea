import {
    AnnotationOptions,
    AnnotationType,
    AssetAnnotation,
    OnNewAnnotation,
    Point,
} from './annotationTypes.ts';
import {DrawAnnotationHandler} from './DrawAnnotationHandler.ts';
import {RectAnnotationHandler} from './RectAnnotationHandler.ts';
import {PointAnnotationHandler} from './PointAnnotationHandler.ts';
import {CircleAnnotationHandler} from './CircleAnnotationHandler.ts';
import {HighlightAnnotationHandler} from './HighlightAnnotationHandler.ts';

export type StartingPoint = Point;

type Clear = () => void;

type BaseEvent = {
    canvas: HTMLCanvasElement;
    context: CanvasRenderingContext2D;
    startingPoint: StartingPoint;
    data: any;
    options: AnnotationOptions;
};

type OnStartDrawingEvent = {} & Point & BaseEvent;

type OnDrawMoveEvent = {
    deltaX: number;
    deltaY: number;
    clear: Clear;
} & Point &
    BaseEvent;

type OnEndDrawingEvent = {
    deltaX: number;
    deltaY: number;
    onNewAnnotation: OnNewAnnotation;
    terminate: () => void;
    relativeX: (x: number) => number;
    relativeY: (y: number) => number;
} & Point &
    BaseEvent;

type OnTerminateEvent = {
    onNewAnnotation: OnNewAnnotation;
    relativeX: (x: number) => number;
    relativeY: (y: number) => number;
} & BaseEvent;

type OnStartDrawing = (event: OnStartDrawingEvent) => void;
type OnDrawMove = (event: OnDrawMoveEvent) => void;
type OnEndDrawing = (event: OnEndDrawingEvent) => void;
type OnTerminate = (event: OnTerminateEvent) => void;

type DrawAnnotationProps = {
    annotation: AssetAnnotation;
    context: CanvasRenderingContext2D;
    toX: (relativeX: number) => number;
    toY: (relativeY: number) => number;
};

export type DrawingHandler = {
    onDrawStart: OnStartDrawing;
    onDrawMove: OnDrawMove;
    onDrawEnd: OnEndDrawing;
    onTerminate: OnTerminate;
    drawAnnotation: (props: DrawAnnotationProps) => void;
};

export const drawingHandlers: Record<AnnotationType, DrawingHandler> = {
    [AnnotationType.Circle]: CircleAnnotationHandler,
    [AnnotationType.Point]: PointAnnotationHandler,
    [AnnotationType.Rect]: RectAnnotationHandler,
    [AnnotationType.Draw]: DrawAnnotationHandler,
    [AnnotationType.Highlight]: HighlightAnnotationHandler,
} as Record<AnnotationType, DrawingHandler>;
