import {RefObject, useEffect, useRef} from 'react';
import {getDrawingHandler} from './handlers';
import type {DrawContext, StartingPoint} from './handlerTypes';
import type {
    AnnotationOptions,
    AnnotationsControl,
    AnnotationType,
    AssetAnnotation,
    CanvasRef,
    SelectedAnnotationRef,
    ShapeControlRef,
    StateSetter,
    ZoomRef,
} from './types';
import {bindEditCanvas} from './editCanvas';
import {renderAnnotations} from './renderAnnotation';
import {getZoomFromRef} from './common';

export type CommonAnnotationDrawProps = {
    annotations: AssetAnnotation[];
    canvasRef: CanvasRef;
    selectedAnnotationRef: SelectedAnnotationRef;
    setAnnotationOptions: StateSetter<AnnotationOptions>;
    /** Space bar held: the pointer pans the image instead */
    spaceRef: RefObject<boolean>;
    zoomRef: ZoomRef;
    shapeControlRef: ShapeControlRef;
};

type Props = {
    annotationsControl: AnnotationsControl | undefined;
    /** The active tool, none for the selection mode */
    mode: AnnotationType | undefined;
    annotationOptions: AnnotationOptions;
    onTerminate: () => void;
    onSelect: (annotation: AssetAnnotation | undefined) => void;
    page?: number;
    /** Nothing is bound when false (read only display) */
    enabled: boolean;
} & CommonAnnotationDrawProps;

/**
 * Binds the mouse to the canvas: the active tool draws a new shape, the
 * selection mode (no tool) edits the existing ones.
 */
export function useAnnotationDraw({
    canvasRef,
    annotationsControl,
    onTerminate: onTerminateProp,
    onSelect,
    mode,
    annotationOptions,
    setAnnotationOptions,
    selectedAnnotationRef,
    annotations,
    page,
    spaceRef,
    shapeControlRef,
    zoomRef,
    enabled,
}: Props) {
    const startingPoint = useRef<StartingPoint | undefined>(undefined);
    const dataRef = useRef<Record<string, any> | undefined>(undefined);
    const previouslySelectedAnnotations = useRef<AssetAnnotation[]>([]);

    useEffect(() => {
        if (!enabled || !annotationsControl || !canvasRef.current) {
            return;
        }

        if (mode) {
            selectedAnnotationRef.current = undefined;
        }

        const canvas = canvasRef.current;
        const context = canvas.getContext('2d')!;
        const clear = () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            renderAnnotations({
                canvasRef,
                annotations,
                page,
                selectedAnnotationRef,
                zoomRef,
                shapeControlRef,
            });
        };

        const handler = getDrawingHandler(mode);
        if (mode && handler) {
            const parent = canvas.parentNode as HTMLElement;
            const {offsetWidth: width, offsetHeight: height} = parent;
            const relativeX = (x: number) => x / width;
            const relativeY = (y: number) => y / height;

            const {onDrawStart, onDrawMove, onDrawEnd, onTerminate} = handler;

            const reset = () => {
                canvas.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                window.removeEventListener('contextmenu', onStopHandler);
                window.removeEventListener('keydown', onKeyDown, true);
                startingPoint.current = undefined;
                dataRef.current = undefined;
            };

            const drawContext: DrawContext = {
                context,
                zoom: getZoomFromRef(zoomRef),
            };

            const onMouseMove = (event: MouseEvent) => {
                const x = event.offsetX;
                const y = event.offsetY;
                const st = startingPoint.current!;

                onDrawMove({
                    options: annotationOptions,
                    data: dataRef.current!,
                    drawContext,
                    canvas,
                    startingPoint: st,
                    x,
                    y,
                    deltaX: x - st.x,
                    deltaY: y - st.y,
                    clear,
                });
            };

            const terminateHandler = () => {
                onTerminate({
                    options: annotationOptions,
                    data: dataRef.current ?? {},
                    drawContext,
                    onNewAnnotation: annotationsControl.addAnnotation,
                    canvas,
                    startingPoint: startingPoint.current ?? {x: 0, y: 0},
                    relativeX,
                    relativeY,
                    clear,
                });
                onTerminateProp();
                reset();
            };

            const cancelHandler = () => {
                onTerminate({
                    options: annotationOptions,
                    data: dataRef.current ?? {},
                    drawContext,
                    onNewAnnotation: () => {},
                    canvas,
                    startingPoint: startingPoint.current ?? {x: 0, y: 0},
                    relativeX,
                    relativeY,
                    clear,
                });
                onTerminateProp();
                reset();
                clear();
            };

            const onMouseUp = (event: MouseEvent) => {
                canvas.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                const st = startingPoint.current!;
                const x = event.offsetX;
                const y = event.offsetY;

                onDrawEnd({
                    options: annotationOptions,
                    data: dataRef.current!,
                    drawContext,
                    onNewAnnotation: annotationsControl.addAnnotation,
                    terminate: terminateHandler,
                    canvas,
                    startingPoint: st,
                    x,
                    y,
                    deltaX: x - st.x,
                    deltaY: y - st.y,
                    relativeX,
                    relativeY,
                    clear,
                });
            };

            // Escape cancels the drawing in progress (and leaves the tool),
            // before the viewer gets the key
            const onKeyDown = (event: KeyboardEvent) => {
                if (event.key !== 'Escape') {
                    return;
                }
                event.stopPropagation();
                event.stopImmediatePropagation();
                cancelHandler();
            };

            // A right click ends a free hand drawing
            const onStopHandler = (event: Event) => {
                event.preventDefault();
                terminateHandler();
            };

            window.addEventListener('contextmenu', onStopHandler);
            window.addEventListener('keydown', onKeyDown, true);

            const onMouseDown = (event: MouseEvent) => {
                if (spaceRef.current || event.button !== 0) {
                    return;
                }
                event.stopPropagation();
                event.preventDefault();
                const activeElement = document.activeElement;
                if (activeElement && activeElement instanceof HTMLElement) {
                    activeElement.blur();
                }

                const x = event.offsetX;
                const y = event.offsetY;

                startingPoint.current = {x, y};
                dataRef.current ??= {};

                onDrawStart({
                    options: annotationOptions,
                    data: dataRef.current,
                    drawContext,
                    canvas,
                    startingPoint: startingPoint.current,
                    x,
                    y,
                    clear,
                    terminate: terminateHandler,
                    onNewAnnotation: annotationsControl.addAnnotation,
                    relativeY,
                    relativeX,
                });

                canvas.addEventListener('mousemove', onMouseMove);
                window.addEventListener('mouseup', onMouseUp);
            };

            canvas.addEventListener('mousedown', onMouseDown);

            return () => {
                canvas.removeEventListener('mousedown', onMouseDown);
                reset();
            };
        } else if (!mode) {
            return bindEditCanvas({
                canvasRef,
                annotations,
                startingPoint,
                clear,
                selectedAnnotationRef,
                onUpdate: annotationsControl.updateAnnotation,
                onSelect,
                setAnnotationOptions,
                spaceRef,
                zoomRef,
                previouslySelectedAnnotations,
                shapeControlRef,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        enabled,
        canvasRef,
        mode,
        annotationOptions,
        annotationsControl,
        annotations,
        page,
    ]);
}
