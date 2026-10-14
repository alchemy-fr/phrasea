import type {RefObject} from 'react';
import type {
    AnnotationType,
    AssetAnnotation,
    OnUpdateAnnotation,
} from './types';
import type {
    DrawContext,
    OnResizeEvent,
    StartingPoint,
    ToFunction,
} from './handlerTypes';
import {getDrawingHandler} from './handlers';
import type {CommonAnnotationDrawProps} from './useAnnotationDraw';
import {getZoomFromRef} from './common';
import {
    getMoveCircleCoordsInRectangle,
    isPointInRectangle,
} from './shapes/rectangle';
import {isPointInCircle} from './shapes/circle';
import {updateLastOptions} from './defaultOptions';

type UnregisterFunction = () => void;

type Props = {
    clear: () => void;
    onUpdate: OnUpdateAnnotation;
    onSelect: (annotation: AssetAnnotation | undefined) => void;
    previouslySelectedAnnotations: RefObject<AssetAnnotation[]>;
    startingPoint: RefObject<StartingPoint | undefined>;
} & CommonAnnotationDrawProps;

/**
 * Selection mode (no tool active): a click selects the shape under the
 * pointer (cycling through overlapping ones), a drag on a handle of the
 * selected shape moves or resizes it.
 */
export function bindEditCanvas({
    annotations,
    canvasRef,
    clear,
    selectedAnnotationRef,
    onUpdate,
    onSelect,
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

    const drawContext: DrawContext = {
        context,
        zoom: getZoomFromRef(zoomRef),
    };

    const onMouseDown = (e: MouseEvent) => {
        if (spaceRef.current || e.button !== 0) {
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
            );

            if (!annotation?.editable) {
                return false;
            }

            const handler = getDrawingHandler(annotation.type);
            if (!handler) {
                return false;
            }
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
                    (allowMove &&
                        isPointInRectangle(
                            e.offsetX,
                            e.offsetY,
                            boundingBox
                        )) ||
                    isPointInCircle(
                        e.offsetX,
                        e.offsetY,
                        getMoveCircleCoordsInRectangle(drawContext, boundingBox)
                    )
                ) {
                    resizeHandler = handler.getMoveHandler();
                }
            }

            if (!resizeHandler) {
                return false;
            }

            clear();

            const mouseMove = (e: MouseEvent) => {
                const x = e.offsetX;
                const y = e.offsetY;
                const st = startingPoint.current!;

                e.preventDefault();

                updatedAnnotation = resizeHandler!({
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
                updateLastOptions(
                    updatedAnnotation.type as AnnotationType,
                    newOptions
                );
                if (updatedAnnotation.editable) {
                    setAnnotationOptions(newOptions);
                }

                selectedAnnotationRef.current = updatedAnnotation;
                clear();
            };
            const onMouseUp = () => {
                if (shapeControlRef.current) {
                    shapeControlRef.current.style.pointerEvents = 'auto';
                }
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

            if (shapeControlRef.current) {
                shapeControlRef.current.style.pointerEvents = 'none';
            }

            return true;
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

            const handler = getDrawingHandler(annotation.type);
            if (handler) {
                setAnnotationOptions(handler.toOptions(annotation, {toX, toY}));
            }

            e.preventDefault();
        }

        onSelect(selectedAnnotationRef.current);

        if (!selectedAnnotationRef.current) {
            clear();
        } else {
            initMouseListeners(true);
        }
    };

    canvas.addEventListener('mousedown', onMouseDown);

    return () => {
        canvas.removeEventListener('mousedown', onMouseDown);
    };
}

/**
 * The shape under the point. Clicking again on overlapping shapes cycles
 * through them: the ones already picked come last.
 */
function getBestSelectionCandidate(
    drawContext: DrawContext,
    annotations: AssetAnnotation[],
    previouslySelectedAnnotations: RefObject<AssetAnnotation[]>,
    offsetX: number,
    offsetY: number,
    toX: ToFunction,
    toY: ToFunction
): AssetAnnotation | undefined {
    const candidates: AssetAnnotation[] = [];
    for (const annotation of annotations) {
        const handler = getDrawingHandler(annotation.type);
        if (!handler) {
            continue;
        }

        const boundingBox = handler.getBoundingBox({
            drawContext,
            annotation,
            toX,
            toY,
            options: handler.toOptions(annotation, {toX, toY}),
        });
        if (isPointInRectangle(offsetX, offsetY, boundingBox)) {
            if (
                !previouslySelectedAnnotations.current.some(
                    a => a.id === annotation.id
                )
            ) {
                return annotation;
            }
            candidates.push(annotation);
        }
    }

    if (candidates.length > 0) {
        const annotation = previouslySelectedAnnotations.current.find(a =>
            candidates.some(c => c.id === a.id)
        );
        previouslySelectedAnnotations.current = [];

        return annotation ?? candidates[0];
    }

    return;
}
