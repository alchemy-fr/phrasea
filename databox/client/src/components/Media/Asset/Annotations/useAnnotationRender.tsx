import React, {useCallback} from 'react';
import {drawingHandlers} from './events.ts';
import {AssetAnnotation} from './annotationTypes.ts';

type Props = {
    canvasRef: React.MutableRefObject<HTMLCanvasElement | null>;
    annotations: AssetAnnotation[] | undefined;
    page?: number;
    selectedAnnotation?: React.MutableRefObject<AssetAnnotation | undefined>;
};

export function renderAnnotations({
    canvasRef,
    annotations,
    page,
    selectedAnnotation,
}: Props) {
    if (canvasRef.current) {
        const canvas = canvasRef.current;
        const parent = canvas.parentNode as HTMLDivElement;
        const {offsetWidth: width, offsetHeight: height} = parent;

        const resolution = Math.max(devicePixelRatio, 2);
        canvas.width = width * resolution;
        canvas.height = height * resolution;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        const context = canvas!.getContext('2d')!;
        context.scale(resolution, resolution);

        (annotations ?? [])
            .filter(
                a =>
                    (!page || a.page === page) &&
                    (!selectedAnnotation || selectedAnnotation.current !== a)
            )
            .forEach(annotation => {
                const handler = drawingHandlers[annotation.type];
                if (handler) {
                    context.globalAlpha = 1;
                    handler.drawAnnotation({
                        context,
                        annotation,
                        toX: x => x * width,
                        toY: y => y * height,
                    });
                }
            });
    }
}

export function useAnnotationRender({canvasRef, annotations, page}: Props) {
    const render = useCallback(() => {
        renderAnnotations({
            canvasRef,
            annotations,
            page,
        });
    }, [canvasRef, annotations]);

    React.useEffect(() => {
        render();
    }, [render]);

    return {
        render,
    };
}
