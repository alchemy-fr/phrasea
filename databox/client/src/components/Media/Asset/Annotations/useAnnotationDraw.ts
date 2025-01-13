import React, {useRef} from 'react';
import {drawingHandlers, StartingPoint} from './events.ts';
import {
    AnnotationOptions,
    AnnotationType,
    OnNewAnnotation,
} from './annotationTypes.ts';

type Props = {
    canvasRef: React.MutableRefObject<HTMLCanvasElement | null>;
    onNewAnnotation: OnNewAnnotation | undefined;
    mode: AnnotationType | undefined;
    annotationOptions: AnnotationOptions;
    onTerminate: () => void;
};

export function useAnnotationDraw({
    canvasRef,
    onNewAnnotation,
    onTerminate: onTerminateProp,
    mode,
    annotationOptions,
}: Props) {
    const startingPoint = useRef<StartingPoint | undefined>();
    const dataRef = useRef<object | undefined>();

    React.useEffect(() => {
        if (
            onNewAnnotation &&
            mode &&
            canvasRef.current &&
            mode in drawingHandlers
        ) {
            const canvas = canvasRef.current;
            const parent = canvas.parentNode as HTMLDivElement;
            const {offsetWidth: width, offsetHeight: height} = parent;
            const relativeX = (x: number) => x / width;
            const relativeY = (y: number) => y / height;

            const {onDrawStart, onDrawMove, onDrawEnd, onTerminate} =
                drawingHandlers[mode];

            const resolution = Math.max(devicePixelRatio, 2);
            canvas.width = width * resolution;
            canvas.height = height * resolution;
            canvas.style.width = width + 'px';
            canvas.style.height = height + 'px';

            const context = canvas!.getContext('2d')!;
            context.scale(resolution, resolution);

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
                    clear: () =>
                        context.clearRect(0, 0, canvas.width, canvas.height),
                });
            };

            const terminateHandler = () => {
                onTerminate({
                    options: annotationOptions,
                    data: dataRef.current!,
                    context,
                    onNewAnnotation,
                    canvas,
                    startingPoint: startingPoint.current!,
                    relativeX,
                    relativeY,
                });
                onTerminateProp();
                reset();
            };

            const cancelHandler = () => {
                onTerminate({
                    options: annotationOptions,
                    data: dataRef.current!,
                    context,
                    onNewAnnotation: () => {},
                    canvas,
                    startingPoint: startingPoint.current!,
                    relativeX,
                    relativeY,
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
                    onNewAnnotation,
                    terminate: terminateHandler,
                    canvas,
                    startingPoint: st,
                    x,
                    y,
                    deltaX: x - st.x,
                    deltaY: y - st.y,
                    relativeX,
                    relativeY,
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
                });

                canvas.addEventListener('mousemove', onMouseMove);
                window.addEventListener('mouseup', onMouseUp);
            };

            canvas.addEventListener('mousedown', onMouseDown);

            return () => {
                canvas.removeEventListener('mousedown', onMouseDown);
                reset();
            };
        }
    }, [canvasRef, mode, annotationOptions]);
}
