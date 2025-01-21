import React, {useRef} from 'react';
import {drawingHandlers, StartingPoint} from './events.ts';
import {
    AnnotationOptions,
    AnnotationsControl,
    AnnotationType,
    AssetAnnotation,
    SelectedAnnotationRef,
} from './annotationTypes.ts';
import {bindEditCanvas} from './editCanvas.ts';
import {renderAnnotations} from './useAnnotationRender.tsx';
import {StateSetter} from "../../../../types.ts";

type Props = {
    canvasRef: React.MutableRefObject<HTMLCanvasElement | null>;
    annotationsControl: AnnotationsControl | undefined;
    selectedAnnotationRef: SelectedAnnotationRef;
    mode: AnnotationType | undefined;
    annotationOptions: AnnotationOptions;
    setAnnotationOptions: StateSetter<AnnotationOptions>;
    onTerminate: () => void;
    annotations: AssetAnnotation[] | undefined;
    page?: number;
    zoomStep?: number | undefined;
};

export function useAnnotationDraw({
    canvasRef,
    annotationsControl,
    onTerminate: onTerminateProp,
    mode,
    annotationOptions,
    setAnnotationOptions,
    selectedAnnotationRef,
    annotations,
    page,
    zoomStep,
}: Props) {
    const startingPoint = useRef<StartingPoint | undefined>();
    const dataRef = useRef<object | undefined>();

    React.useEffect(() => {
        if (!annotationsControl || !canvasRef.current) {
            return;
        }

        if (mode) {
            selectedAnnotationRef.current = undefined;
        }

        const canvas = canvasRef.current;
        const context = canvas!.getContext('2d')!;
        const clear = () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            renderAnnotations({
                canvasRef,
                annotations,
                page,
                selectedAnnotationRef: selectedAnnotationRef,
                zoomStep,
            });
        };

        if (mode && mode in drawingHandlers) {
            const parent = canvas.parentNode as HTMLDivElement;
            const {offsetWidth: width, offsetHeight: height} = parent;
            const relativeX = (x: number) => x / width;
            const relativeY = (y: number) => y / height;

            const {onDrawStart, onDrawMove, onDrawEnd, onTerminate} =
                drawingHandlers[mode];

            const reset = () => {
                canvas.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                stopEvents.forEach(event => {
                    window.removeEventListener(event, onStopHandler);
                });
                cancelEvents.forEach(event => {
                    window.removeEventListener(event, onCancel);
                });
                startingPoint.current = undefined;
                dataRef.current = undefined;
            };

            const onMouseMove = (event: MouseEvent) => {
                const x = event.offsetX;
                const y = event.offsetY;

                const st = startingPoint.current!;

                onDrawMove({
                    options: annotationOptions,
                    data: dataRef.current!,
                    context,
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
                    data: dataRef.current!,
                    context,
                    onNewAnnotation: annotationsControl.onNew,
                    canvas,
                    startingPoint: startingPoint.current!,
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
                    data: dataRef.current!,
                    context,
                    onNewAnnotation: () => {
                    },
                    canvas,
                    startingPoint: startingPoint.current!,
                    relativeX,
                    relativeY,
                    clear,
                });
                onTerminateProp();
                reset();
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
                    context,
                    onNewAnnotation: annotationsControl.onNew,
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

            const onCancel = (event: any) => {
                if (
                    event.type === 'keydown' &&
                    (event as KeyboardEvent).key !== 'Escape'
                ) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                cancelHandler();
            };

            const onStopHandler = (event: any) => {
                if (
                    event.type === 'keydown' &&
                    (event as KeyboardEvent).key === 'Escape'
                ) {
                    event.stopPropagation();
                    return;
                }
                event.preventDefault();

                terminateHandler();
            };

            const stopEvents = ['contextmenu', 'keydown'];
            const cancelEvents = ['keydown'];
            stopEvents.forEach(event => {
                window.addEventListener(event, onStopHandler);
            });
            cancelEvents.forEach(event => {
                window.addEventListener(event, onCancel);
            });

            const onMouseDown = (event: MouseEvent) => {
                event.preventDefault();
                event.stopPropagation();
                const x = event.offsetX;
                const y = event.offsetY;

                startingPoint.current = {
                    x,
                    y,
                };

                dataRef.current ??= {};

                onDrawStart({
                    options: annotationOptions,
                    data: dataRef.current!,
                    context,
                    canvas,
                    startingPoint: startingPoint.current!,
                    x,
                    y,
                    clear,
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
                canvas: canvasRef.current!,
                annotations,
                clear,
                selectedAnnotationRef,
                onUpdate: annotationsControl.onUpdate,
                setAnnotationOptions,
                zoomStep,
            });
        }
    }, [canvasRef, mode, annotationOptions, annotationsControl, annotations, page]);
}
