import {
    AnnotationOptions,
    AnnotationType,
    AssetAnnotation,
    OnNewAnnotation,
    Point,
} from './annotationTypes.ts';
import {DrawAnnotationHandler} from './shapes/DrawAnnotationHandler.ts';
import {RectAnnotationHandler} from './shapes/RectAnnotationHandler.ts';
import {TargetAnnotationHandler} from './shapes/TargetAnnotationHandler.ts';
import {CircleAnnotationHandler} from './shapes/CircleAnnotationHandler.ts';
import {LineAnnotationHandler} from "./shapes/LineAnnotationHandler.ts";
import {ArrowAnnotationHandler} from "./shapes/ArrowAnnotationHandler.ts";
import {TextAnnotationHandler} from "./shapes/TextAnnotationHandler.ts";

export type StartingPoint = Point;

type Clear = () => void;

type BaseEvent = {
    canvas: HTMLCanvasElement;
    drawContext: DrawContext;
    startingPoint: StartingPoint;
    data: any;
    options: AnnotationOptions;
    clear: Clear;
};

type Terminate = () => void;

type OnStartDrawingEvent = TerminateProps & Point & BaseEvent;

type OnDrawMoveEvent = {
    deltaX: number;
    deltaY: number;
} & Point &
    BaseEvent;

export type ToFunction = (n: number) => number;

type TerminateProps = {
    onNewAnnotation: OnNewAnnotation;
    terminate: Terminate;
    relativeX: ToFunction;
    relativeY: ToFunction;
}

type OnEndDrawingEvent = {
    deltaX: number;
    deltaY: number;
} & TerminateProps & Point &
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

export type DrawContext = {
    context: CanvasRenderingContext2D;
    zoom: number;
}

type DrawAnnotationProps = {
    annotation: AssetAnnotation;
    drawContext: DrawContext;
    toX: ToFunction;
    toY: ToFunction;
};

type PointInsideProps = {
    drawContext: DrawContext;
    annotation: AssetAnnotation;
    x: number;
    y: number;
    toX: ToFunction;
    toY: ToFunction;
};

export type OnResizeEvent = {
    annotation: AssetAnnotation;
    drawContext: DrawContext;
    x: number;
    y: number;
    deltaX: number;
    deltaY: number;
    relativeX: ToFunction;
    relativeY: ToFunction;
};

export type AnnotationResizeHandler = (event: OnResizeEvent) => AssetAnnotation;

export type GetBoundingBoxProps = {
    drawContext: DrawContext;
    annotation: AssetAnnotation;
    options: AnnotationOptions;
    toX: ToFunction;
    toY: ToFunction;
}

type OnRenameEvent = {
    annotation: AssetAnnotation;
    newName: string;
}

export type DrawingHandler = {
    onDrawStart: OnStartDrawing;
    onDrawMove: OnDrawMove;
    onDrawEnd: OnEndDrawing;
    onTerminate: OnTerminate;
    drawAnnotation: (props: DrawAnnotationProps, selected?: boolean) => void;
    toOptions: (annotation: AssetAnnotation, helpers: {
        toX: ToFunction,
        toY: ToFunction,
    }) => AnnotationOptions;
    fromOptions: (options: AnnotationOptions, annotation: AssetAnnotation, helpers: {
        relativeX: ToFunction;
        relativeY: ToFunction;
    }) => AssetAnnotation;
    getBoundingBox: (props: GetBoundingBoxProps) => {
        x: number;
        y: number;
        w: number;
        h: number;
    },
    getResizeHandler: (
        props: PointInsideProps
    ) => AnnotationResizeHandler | undefined;
    getMoveHandler: () => AnnotationResizeHandler;
    onRename?: (props: OnRenameEvent) => AssetAnnotation;
};

export const drawingHandlers: Record<AnnotationType, DrawingHandler> = {
    [AnnotationType.Circle]: CircleAnnotationHandler,
    [AnnotationType.Target]: TargetAnnotationHandler,
    [AnnotationType.Rect]: RectAnnotationHandler,
    [AnnotationType.Draw]: DrawAnnotationHandler,
    [AnnotationType.Line]: LineAnnotationHandler,
    [AnnotationType.Arrow]: ArrowAnnotationHandler,
    [AnnotationType.Text]: TextAnnotationHandler,
} as Record<AnnotationType, DrawingHandler>;
