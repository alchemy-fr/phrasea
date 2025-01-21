import {drawingHandlers} from "./events.ts";
import React from "react";
import {AssetAnnotation, SelectedAnnotationRef} from "./annotationTypes.ts";
import {getZoomFromRef, ZoomRef} from "./common.ts";

type Props = {
    canvasRef: React.MutableRefObject<HTMLCanvasElement | null>;
    annotations: AssetAnnotation[] | undefined;
    page?: number;
    selectedAnnotationRef?: SelectedAnnotationRef;
    zoomRef: ZoomRef;
};

export type {Props as RenderAnnotationProps};

export function renderAnnotations({
    canvasRef,
    annotations,
    page,
    selectedAnnotationRef,
    zoomRef,
}: Props) {
    if (canvasRef.current) {
        const canvas = canvasRef.current;
        const parent = canvas.parentNode as HTMLDivElement;
        const {offsetWidth: width, offsetHeight: height} = parent;

        const resolution = Math.min(devicePixelRatio, 4) * Math.min(zoomRef.current ?? 1, 3);
        canvas.width = width * resolution;
        canvas.height = height * resolution;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        const context = canvas!.getContext('2d')!;
        context.scale(resolution, resolution);

        const selected = selectedAnnotationRef?.current;

        const drawContext = {
            context,
            zoom: getZoomFromRef(zoomRef),
        };

        (annotations ?? [])
            .filter(
                a =>
                    (!page || a.page === page) &&
                    (!selected || selected.id !== a.id)
            )
            .concat(selected ? [selected] : [])
            .forEach(annotation => {
                const handler = drawingHandlers[annotation.type];
                if (handler) {
                    context.globalAlpha = 1;
                    handler.drawAnnotation({
                        drawContext,
                        annotation,
                        toX: x => x * width,
                        toY: y => y * height,
                    }, selected && selected.id === annotation.id);
                }
            });
    }
}
