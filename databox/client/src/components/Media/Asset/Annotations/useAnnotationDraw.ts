import React, {useRef} from "react";
import {drawingHandlers, StartingPoint} from "./events.ts";
import {AnnotationOptions, AnnotationType, OnNewAnnotation} from "./annotationTypes.ts";


type Props = {
    canvasRef: React.MutableRefObject<HTMLCanvasElement | null>;
    onNewAnnotation: OnNewAnnotation | undefined;
    mode: AnnotationType | undefined;
    annotationOptions: AnnotationOptions;
};

export function useAnnotationDraw({
    canvasRef,
    onNewAnnotation,
    mode,
    annotationOptions,
}: Props) {
    const startingPoint = useRef<StartingPoint | undefined>();
    const dataRef = useRef<object | undefined>();

    React.useEffect(() => {
        if (onNewAnnotation && mode && canvasRef.current && mode in drawingHandlers) {
            const canvas = canvasRef.current;
            const parent = canvas.parentNode as HTMLDivElement;
            const parentRect = parent.getBoundingClientRect();
            const {width, height} = parentRect;

            const {
                onStart,
                onMove,
                onEnd,
            } = drawingHandlers[mode];

            var resolution = Math.max(devicePixelRatio, 2);
            canvas.width = width * resolution;
            canvas.height = height * resolution;

            canvas.style.width = width + "px";
            canvas.style.height = height + "px";

            const context = canvas!.getContext('2d')!;
            context.scale(resolution, resolution);

            const onMouseMove = (event: MouseEvent) => {
                console.log('event', event);
                const x = event.offsetX;
                const y = event.offsetY;

                const st = startingPoint.current!;

                onMove({
                    options: annotationOptions,
                    data: dataRef.current,
                    context,
                    canvas,
                    startingPoint: st,
                    x,
                    y,
                    deltaX: x - st.x,
                    deltaY: y - st.y,
                    clear: () => context.clearRect(0, 0, canvas.width, canvas.height),
                });
            }
            const onMouseUp = (event: MouseEvent) => {
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                const st = startingPoint.current!;
                const x = event.offsetX;
                const y = event.offsetY;

                onEnd({
                    options: annotationOptions,
                    data: dataRef.current,
                    context,
                    onNewAnnotation,
                    canvas,
                    startingPoint: st,
                    x,
                    y,
                    deltaX: x - st.x,
                    deltaY: y - st.y,
                    relativeX: (x: number) => x / width,
                    relativeY: (y: number) => y / height,
                });
            }

            const onMouseDown = (event: MouseEvent) => {
                event.preventDefault();
                event.stopPropagation();
                const x = event.offsetX;
                const y = event.offsetY;

                startingPoint.current = {
                    x,
                    y,
                };

                dataRef.current = {};

                requestAnimationFrame(() => {
                    onStart({
                        options: annotationOptions,
                        data: dataRef.current,
                        context,
                        canvas,
                        startingPoint: startingPoint.current!,
                        x,
                        y,
                    });
                });

                canvas.addEventListener('mousemove', onMouseMove);
                window.addEventListener('mouseup', onMouseUp);
            };

            canvas.addEventListener('mousedown', onMouseDown);

            return () => {
                canvas.removeEventListener('mousedown', onMouseDown);
                canvas.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
            }
        }
    }, [canvasRef, mode, annotationOptions]);
}

