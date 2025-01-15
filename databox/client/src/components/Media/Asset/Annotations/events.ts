import {
    AnnotationOptions,
    AnnotationType,
    AssetAnnotation,
    OnNewAnnotation,
    Point,
} from './annotationTypes.ts';
import {DrawAnnotationHandler} from './shapes/DrawAnnotationHandler.ts';
import {RectAnnotationHandler} from './shapes/RectAnnotationHandler.ts';
import {PointAnnotationHandler} from './shapes/PointAnnotationHandler.ts';
import {CircleAnnotationHandler} from './shapes/CircleAnnotationHandler.ts';

export type StartingPoint = Point;

type Clear = () => void;

type BaseEvent = {
    canvas: HTMLCanvasElement;
    context: CanvasRenderingContext2D;
    startingPoint: StartingPoint;
    data: any;
    options: AnnotationOptions;
    clear: Clear;
};

type OnStartDrawingEvent = {} & Point & BaseEvent;

type OnDrawMoveEvent = {
    deltaX: number;
    deltaY: number;
} & Point &
    BaseEvent;

type ToFunction = (n: number) => number;

type OnEndDrawingEvent = {
    deltaX: number;
    deltaY: number;
    onNewAnnotation: OnNewAnnotation;
    terminate: () => void;
    relativeX: ToFunction;
    relativeY: ToFunction;
} & Point &
    BaseEvent;

type OnTerminateEvent = {
    onNewAnnotation: OnNewAnnotation;
    relativeX: ToFunction;
    relativeY: ToFunction;
} & BaseEvent;

type OnStartDrawing = (event: OnStartDrawingEvent) => void;
type OnDrawMove = (event: OnDrawMoveEvent) => void;
type OnEndDrawing = (event: OnEndDrawingEvent) => void;
type OnTerminate = (event: OnTerminateEvent) => void;

type DrawAnnotationProps = {
    annotation: AssetAnnotation;
    context: CanvasRenderingContext2D;
    toX: ToFunction;
    toY: ToFunction;
};

type PointInsideProps = {
    annotation: AssetAnnotation;
    x: number;
    y: number;
    toX: ToFunction;
    toY: ToFunction;
};

export type OnResizeEvent = {
    annotation: AssetAnnotation;
    context: CanvasRenderingContext2D;
    x: number;
    y: number;
    relativeX: ToFunction;
    relativeY: ToFunction;
};

export type AnnotationResizeHandler = (event: OnResizeEvent) => AssetAnnotation;

export type DrawingHandler = {
    onDrawStart: OnStartDrawing;
    onDrawMove: OnDrawMove;
    onDrawEnd: OnEndDrawing;
    onTerminate: OnTerminate;
    drawAnnotation: (props: DrawAnnotationProps, selected?: boolean) => void;
    isPointInside: (props: PointInsideProps) => boolean;
    getResizeHandler: (
        props: PointInsideProps
    ) => AnnotationResizeHandler | undefined;
};

export const drawingHandlers: Record<AnnotationType, DrawingHandler> = {
    [AnnotationType.Circle]: CircleAnnotationHandler,
    [AnnotationType.Point]: PointAnnotationHandler,
    [AnnotationType.Rect]: RectAnnotationHandler,
    [AnnotationType.Draw]: DrawAnnotationHandler,
} as Record<AnnotationType, DrawingHandler>;
