import {AnnotationOptions, AssetAnnotation, OnUpdateAnnotation, SelectedAnnotationRef} from './annotationTypes.ts';
import {drawingHandlers, OnResizeEvent} from './events.ts';
import {renderAnnotations} from "./useAnnotationRender.tsx";
import {StateSetter} from "../../../../types.ts";

type UnregisterFunction = () => void;

type Props = {
    annotations: AssetAnnotation[] | undefined;
    canvas: HTMLCanvasElement;
    clear: () => void;
    selectedAnnotationRef: SelectedAnnotationRef;
    onUpdate: OnUpdateAnnotation;
    setAnnotationOptions: StateSetter<AnnotationOptions>;
    zoomStep: number | undefined;
};

export function bindEditCanvas({
    annotations,
    canvas,
    clear,
    selectedAnnotationRef,
    onUpdate,
    setAnnotationOptions,
    zoomStep,
}: Props): UnregisterFunction {
    const context = canvas.getContext('2d')!;
    const width = canvas.offsetWidth;
    const height = canvas.offsetHeight;
    const relativeX = (x: number) => x / width;
    const relativeY = (y: number) => y / height;

    const toX = (x: number) => x * width;
    const toY = (y: number) => y * height;

    const onMouseDown = (e: MouseEvent) => {
        e.preventDefault();

        function initMouseListeners(): boolean {
            const annotation = annotations!.find(a => a.id === selectedAnnotationRef.current!.id!)!;
            const handler = drawingHandlers[annotation.type]!;
            let updatedAnnotation: AssetAnnotation | undefined;

            const resizeHandler = handler.getResizeHandler({
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
                        context,
                        annotation,
                        toX,
                        toY,
                    },
                    true
                );

                const mouseMove = (e: MouseEvent) => {
                    const x = e.offsetX;
                    const y = e.offsetY;

                    updatedAnnotation = resizeHandler({
                        annotation,
                        context,
                        x,
                        y,
                        relativeX,
                        relativeY,
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
        }

        selectedAnnotationRef.current = undefined;

        for (const annotation of annotations ?? []) {
            const handler = drawingHandlers[annotation.type]!;
            const {offsetX, offsetY} = e;
            if (
                handler.isPointInside({
                    annotation,
                    x: offsetX,
                    y: offsetY,
                    toX,
                    toY,
                })
            ) {
                selectedAnnotationRef.current = annotation;
                clear();

                setAnnotationOptions(handler.toOptions(annotation, {
                    toX,
                    toY,
                }));

                handler.drawAnnotation(
                    {
                        context,
                        annotation,
                        toX,
                        toY,
                    },
                    true
                );
                break;
            }
        }

        if (!selectedAnnotationRef.current) {
            clear();
        } else {
            initMouseListeners();
        }
    };

    if (selectedAnnotationRef.current) {
        renderAnnotations({
            canvasRef: {current: canvas},
            annotations: annotations,
            selectedAnnotationRef,
            zoomStep,
        });
    }

    canvas.addEventListener('mousedown', onMouseDown);

    return () => {
        canvas.removeEventListener('mousedown', onMouseDown);
    };
}
