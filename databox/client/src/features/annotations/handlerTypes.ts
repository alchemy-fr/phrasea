import type {
    AnnotationOptions,
    AssetAnnotation,
    DrawOptions,
    OnNewAnnotation,
    Point,
} from './types';
import type {RectangleProps} from './shapes/rectangle';

export type StartingPoint = Point;

export type DrawContext = {
    context: CanvasRenderingContext2D;
    /** Screen pixels per canvas pixel: handles are drawn at a constant screen size */
    zoom: number;
};

/** Canvas pixels <-> relative (0..1) coordinates */
export type ToFunction = (n: number) => number;

type BaseEvent = {
    canvas: HTMLCanvasElement;
    drawContext: DrawContext;
    startingPoint: StartingPoint;
    /** Scratch data of the tool, kept between the events of one drawing */
    data: any;
    options: AnnotationOptions;
    /** Clears the canvas and renders the existing shapes again */
    clear: () => void;
};

type TerminateProps = {
    onNewAnnotation: OnNewAnnotation;
    terminate: () => void;
    relativeX: ToFunction;
    relativeY: ToFunction;
};

type OnStartDrawingEvent = TerminateProps & Point & BaseEvent;

type OnDrawMoveEvent = {
    deltaX: number;
    deltaY: number;
} & Point &
    BaseEvent;

type OnEndDrawingEvent = {
    deltaX: number;
    deltaY: number;
} & TerminateProps &
    Point &
    BaseEvent;

type OnTerminateEvent = {
    onNewAnnotation: OnNewAnnotation;
    relativeX: ToFunction;
    relativeY: ToFunction;
} & BaseEvent;

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
};

type OnRenameEvent = {
    annotation: AssetAnnotation;
    newName: string;
};

/**
 * A tool of the toolbar: draws a kind of shape with the mouse, renders it,
 * and tells how it is selected, moved and resized.
 */
export type DrawingHandler = {
    onDrawStart: (event: OnStartDrawingEvent) => void;
    onDrawMove: (event: OnDrawMoveEvent) => void;
    onDrawEnd: (event: OnEndDrawingEvent) => void;
    /** The tool is left (Escape, right click, another tool) */
    onTerminate: (event: OnTerminateEvent) => void;
    drawAnnotation: (
        props: DrawAnnotationProps,
        drawOptions: DrawOptions
    ) => void;
    /** The toolbar options of an existing shape */
    toOptions: (
        annotation: AssetAnnotation,
        helpers: {toX: ToFunction; toY: ToFunction}
    ) => AnnotationOptions;
    /** Applies the toolbar options to an existing shape */
    fromOptions: (
        options: AnnotationOptions,
        annotation: AssetAnnotation,
        helpers: {relativeX: ToFunction; relativeY: ToFunction}
    ) => AssetAnnotation;
    /** In canvas pixels */
    getBoundingBox: (props: GetBoundingBoxProps) => RectangleProps;
    /** The resize handler of the handle under the point, if any */
    getResizeHandler: (
        props: PointInsideProps
    ) => AnnotationResizeHandler | undefined;
    getMoveHandler: () => AnnotationResizeHandler;
    onRename?: (props: OnRenameEvent) => AssetAnnotation;
};
