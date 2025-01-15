import {AnnotationId, AssetAnnotation, OnUpdateAnnotation} from './annotationTypes.ts';
import {drawingHandlers, OnResizeEvent} from './events.ts';
import {MutableRefObject} from 'react';

type UnregisterFunction = () => void;

type Props = {
    annotations: AssetAnnotation[] | undefined;
    canvas: HTMLCanvasElement;
    clear: () => void;
    selectedAnnotation: MutableRefObject<AnnotationId | undefined>;
    onUpdate: OnUpdateAnnotation;
};

export function bindEditCanvas({
    annotations,
    canvas,
    clear,
    selectedAnnotation,
    onUpdate,
}: Props): UnregisterFunction {
    const onMouseDown = (e: MouseEvent) => {
        const width = canvas.offsetWidth;
        const height = canvas.offsetHeight;
        const relativeX = (x: number) => x / width;
        const relativeY = (y: number) => y / height;

        const toX = (x: number) => x * width;
        const toY = (y: number) => y * height;

        if (selectedAnnotation.current) {
            const annotation = annotations!.find(a => a.id === selectedAnnotation.current)!;
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
                const context = canvas.getContext('2d')!;
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

                    clear();
                    handler.drawAnnotation({
                        annotation: updatedAnnotation,
                        context,
                        toX,
                        toY,
                    }, true);
                };
                const onMouseUp = () => {
                    if (updatedAnnotation) {
                        onUpdate(
                            selectedAnnotation.current!,
                            updatedAnnotation
                        );
                    }

                    canvas.removeEventListener('mousemove', mouseMove);
                    window.removeEventListener('mouseup', onMouseUp);
                };

                canvas.addEventListener('mousemove', mouseMove);
                window.addEventListener('mouseup', onMouseUp);

                return;
            }
        }

        selectedAnnotation.current = undefined;

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
                const context = canvas.getContext('2d')!;

                selectedAnnotation.current = annotation.id;
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
                break;
            }
        }
    };

    canvas.addEventListener('mousedown', onMouseDown);

    return () => {
        canvas.addEventListener('mousedown', onMouseDown);
    };
}
