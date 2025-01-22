import {AssetAnnotation, OnUpdateAnnotation} from './annotationTypes.ts';
import {DrawContext, drawingHandlers, OnResizeEvent, StartingPoint, ToFunction,} from './events.ts';
import {CommonAnnotationDrawProps} from './useAnnotationDraw.ts';
import {getZoomFromRef, ShapeControlRef} from './common.ts';
import {MutableRefObject} from 'react';
import {isPointInRectangle} from './shapes/RectAnnotationHandler.ts';
import {getMoveCircleCoordsInRectangle} from "./shapes/rectangle.ts";
import {isPointInCircle} from "./shapes/circle.ts";
import {updateLastOptions} from "./defaultOptions.ts";

type UnregisterFunction = () => void;

type Props = {
    clear: () => void;
    onUpdate: OnUpdateAnnotation;
    previouslySelectedAnnotations: MutableRefObject<AssetAnnotation[]>;
    startingPoint: MutableRefObject<StartingPoint | undefined>;
    shapeControlRef: ShapeControlRef;
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
    shapeControlRef,
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
    };

    const onMouseDown = (e: MouseEvent) => {
        if (spaceRef.current) {
            return;
        }

        e.preventDefault();
        const activeElement = document.activeElement;
        if (activeElement && activeElement instanceof HTMLElement) {
            activeElement.blur();
        }
        e.stopPropagation();

        startingPoint.current = {
            x: e.offsetX,
            y: e.offsetY,
        };

        function initMouseListeners(allowMove: boolean): boolean {
            const annotation = annotations.find(
                a => a.id === selectedAnnotationRef.current!.id!
            )!;

            if (!annotation.editable) {
                return false;
            }

            const handler = drawingHandlers[annotation.type]!;
            let updatedAnnotation: AssetAnnotation | undefined;

            let resizeHandler = handler.getResizeHandler({
                drawContext,
                annotation,
                toX,
                toY,
                x: e.offsetX,
                y: e.offsetY,
            });

            if (!resizeHandler) {
                const boundingBox = handler.getBoundingBox({
                    drawContext,
                    annotation,
                    toX,
                    toY,
                    options: handler.toOptions(annotation, {toX, toY}),
                });
                if (
                    (allowMove && isPointInRectangle(e.offsetX, e.offsetY, boundingBox))
                    || isPointInCircle(e.offsetX, e.offsetY, getMoveCircleCoordsInRectangle(drawContext, boundingBox))) {
                    resizeHandler = handler.getMoveHandler();
                }
            }

            if (resizeHandler) {
                clear();

                const mouseMove = (e: MouseEvent) => {
                    const x = e.offsetX;
                    const y = e.offsetY;
                    const st = startingPoint.current!;

                    e.preventDefault();

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

                    const newOptions = handler.toOptions(updatedAnnotation, {
                        toX,
                        toY,
                    });
                    updateLastOptions(updatedAnnotation.type, newOptions);
                    if (updatedAnnotation.editable) {
                        setAnnotationOptions(
                            newOptions
                        );
                    }

                    selectedAnnotationRef.current = updatedAnnotation;
                    clear();
                };
                const onMouseUp = () => {
                    shapeControlRef.current!.style.pointerEvents = 'auto';
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

                shapeControlRef.current!.style.pointerEvents = 'none';

                return true;
            }

            return false;
        }

        if (selectedAnnotationRef.current) {
            if (initMouseListeners(false)) {
                return;
            }

            if (previouslySelectedAnnotations.current.length > 20) {
                previouslySelectedAnnotations.current.shift();
            }
            previouslySelectedAnnotations.current.push(
                selectedAnnotationRef.current
            );
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
            toY
        );
        if (annotation?.id) {
            selectedAnnotationRef.current = annotation;
            clear();

            const handler = drawingHandlers[annotation.type]!;
            setAnnotationOptions(
                handler.toOptions(annotation, {
                    toX,
                    toY,
                })
            );

            e.preventDefault();
        }

        if (!selectedAnnotationRef.current) {
            clear();
        } else {
            initMouseListeners(true);
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
    annotations: AssetAnnotation[],
    previouslySelectedAnnotations: MutableRefObject<AssetAnnotation[]>,
    offsetX: number,
    offsetY: number,
    toX: ToFunction,
    toY: ToFunction
): AssetAnnotation | undefined {
    const candidates: AssetAnnotation[] = [];
    for (const annotation of annotations) {
        const handler = drawingHandlers[annotation.type]!;

        const boundingBox = handler.getBoundingBox({
            drawContext,
            annotation,
            toX,
            toY,
            options: handler.toOptions(annotation, {toX, toY}),
        });
        if (isPointInRectangle(offsetX, offsetY, boundingBox)) {
            if (!previouslySelectedAnnotations.current.includes(annotation)) {
                return annotation;
            } else {
                candidates.push(annotation);
            }
        }
    }

    if (candidates.length > 0) {
        const annotation = previouslySelectedAnnotations.current.filter(a =>
            candidates.includes(a)
        )[0]!;
        previouslySelectedAnnotations.current = [];

        return annotation;
    }

    return;
}
