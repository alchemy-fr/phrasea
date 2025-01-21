import {AssetAnnotation, OnUpdateAnnotation} from './annotationTypes.ts';
import {DrawContext, drawingHandlers, OnResizeEvent, StartingPoint, ToFunction} from './events.ts';
import {CommonAnnotationDrawProps} from "./useAnnotationDraw.ts";
import {getZoomFromRef} from "./common.ts";
import {MutableRefObject} from "react";

type UnregisterFunction = () => void;

type Props = {
    clear: () => void;
    onUpdate: OnUpdateAnnotation;
    previouslySelectedAnnotations: MutableRefObject<AssetAnnotation[]>;
    startingPoint: MutableRefObject<StartingPoint | undefined>;
} & CommonAnnotationDrawProps;

export function bindEditCanvas({
    annotations,
    canvasRef,
    clear,
    selectedAnnotationRef,
    onUpdate,
    setAnnotationOptions,
    spaceRef,
    zoomRef,
    startingPoint,
    previouslySelectedAnnotations,
}: Props): UnregisterFunction {
    const canvas = canvasRef.current!;
    const context = canvas.getContext('2d')!;
    const width = canvas.offsetWidth;
    const height = canvas.offsetHeight;
    const relativeX = (x: number) => x / width;
    const relativeY = (y: number) => y / height;

    const toX = (x: number) => x * width;
    const toY = (y: number) => y * height;

    const drawContext = {
        context,
        zoom: getZoomFromRef(zoomRef),
    };


    const onDblClick = (e: MouseEvent) => {
        if (!spaceRef.current) {
            e.stopPropagation();
        }
    }

    const onMouseDown = (e: MouseEvent) => {
        if (!spaceRef.current) {
            e.stopPropagation();
        } else {
            return;
        }

        startingPoint.current = {
            x: e.offsetX,
            y: e.offsetY,
        };

        function initMouseListeners(): boolean {
            const annotation = annotations!.find(a => a.id === selectedAnnotationRef.current!.id!)!;
            const handler = drawingHandlers[annotation.type]!;
            let updatedAnnotation: AssetAnnotation | undefined;

            const resizeHandler = handler.getResizeHandler({
                drawContext,
                annotation,
                toX,
                toY,
                x: e.offsetX,
                y: e.offsetY,
            });

            if (resizeHandler) {
                const toX = (x: number) => x * width;
                const toY = (y: number) => y * height;

                clear();
                handler.drawAnnotation(
                    {
                        drawContext,
                        annotation,
                        toX,
                        toY,
                    },
                    true
                );

                const mouseMove = (e: MouseEvent) => {
                    const x = e.offsetX;
                    const y = e.offsetY;
                    const st = startingPoint.current!;

                    updatedAnnotation = resizeHandler({
                        annotation,
                        drawContext,
                        x,
                        y,
                        relativeX,
                        relativeY,
                        deltaX: x - st.x,
                        deltaY: y - st.y,
                    } as OnResizeEvent);

                    selectedAnnotationRef.current = updatedAnnotation;
                    clear();
                };
                const onMouseUp = () => {
                    if (updatedAnnotation) {
                        selectedAnnotationRef.current = updatedAnnotation;
                        onUpdate(
                            selectedAnnotationRef.current!.id!,
                            updatedAnnotation
                        );
                    }

                    canvas.removeEventListener('mousemove', mouseMove);
                    window.removeEventListener('mouseup', onMouseUp);
                };

                canvas.addEventListener('mousemove', mouseMove);
                window.addEventListener('mouseup', onMouseUp);

                return true;
            }

            return false;
        }

        if (selectedAnnotationRef.current) {
            if (initMouseListeners()) {
                return;
            }

            if (previouslySelectedAnnotations.current.length > 20) {
                previouslySelectedAnnotations.current.shift();
            }
            previouslySelectedAnnotations.current.push(selectedAnnotationRef.current);
        } else {
            previouslySelectedAnnotations.current = [];
        }

        selectedAnnotationRef.current = undefined;

        const {offsetX, offsetY} = e;
        const annotation = getBestSelectionCandidate(
            drawContext,
            annotations,
            previouslySelectedAnnotations,
            offsetX,
            offsetY,
            toX,
            toY,
        );
        if (annotation) {
            selectedAnnotationRef.current = annotation;
            clear();

            const handler = drawingHandlers[annotation.type]!;
            setAnnotationOptions(handler.toOptions(annotation, {
                toX,
                toY,
            }));

            handler.drawAnnotation(
                {
                    drawContext,
                    annotation,
                    toX,
                    toY,
                },
                true
            );
        }

        if (!selectedAnnotationRef.current) {
            clear();
        } else {
            initMouseListeners();
        }
    };

    canvas.addEventListener('mousedown', onMouseDown);
    canvas.addEventListener('dblclick', onDblClick);

    return () => {
        canvas.removeEventListener('mousedown', onMouseDown);
        canvas.removeEventListener('dblclick', onDblClick);
    };
}

function getBestSelectionCandidate(
    drawContext: DrawContext,
    annotations: AssetAnnotation[] | undefined,
    previouslySelectedAnnotations: MutableRefObject<AssetAnnotation[]>,
    offsetX: number,
    offsetY: number,
    toX: ToFunction,
    toY: ToFunction,
): AssetAnnotation | undefined {
    const candidates = [];
    for (const annotation of annotations ?? []) {
        const handler = drawingHandlers[annotation.type]!;
        if (
            handler.isPointInside({
                drawContext,
                annotation,
                x: offsetX,
                y: offsetY,
                toX,
                toY,
            })
        ) {
            if (!previouslySelectedAnnotations.current.includes(annotation)) {
                return annotation;
            } else {
                candidates.push(annotation);
            }
        }
    }

    if (candidates.length > 0) {
        const annotation = previouslySelectedAnnotations.current[0]!;
        previouslySelectedAnnotations.current = [];

        return annotation;
    }

    return
}
