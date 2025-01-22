import {drawingHandlers, ToFunction} from './events.ts';
import React from 'react';
import {AssetAnnotation, SelectedAnnotationRef} from './annotationTypes.ts';
import {getZoomFromRef, ShapeControlRef, ZoomRef} from './common.ts';
import {drawRectangle} from './shapes/rectangle.ts';
import {controlsColor} from './controls.ts';
import {drawCircleControl} from './shapes/circle.ts';

type Props = {
    canvasRef: React.MutableRefObject<HTMLCanvasElement | null>;
    annotations: AssetAnnotation[];
    page?: number;
    selectedAnnotationRef?: SelectedAnnotationRef;
    zoomRef: ZoomRef;
    shapeControlRef: ShapeControlRef;
};

export type {Props as RenderAnnotationProps};

export function renderAnnotations({
    canvasRef,
    annotations,
    page,
    selectedAnnotationRef,
    zoomRef,
    shapeControlRef,
}: Props) {
    if (canvasRef.current) {
        const canvas = canvasRef.current;
        const parent = canvas.parentNode as HTMLDivElement;
        const {offsetWidth: width, offsetHeight: height} = parent;

        const resolution =
            Math.min(devicePixelRatio, 4) * Math.min(zoomRef.current ?? 1, 3);
        canvas.width = width * resolution;
        canvas.height = height * resolution;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        const context = canvas!.getContext('2d')!;
        context.scale(resolution, resolution);

        let selected = selectedAnnotationRef?.current;
        if (selected && !annotations.find(a => a.id === selected!.id)) {
            selected = undefined;
        }

        const drawContext = {
            context,
            zoom: getZoomFromRef(zoomRef),
        };

        annotations
            .filter(
                a =>
                    (!page || a.page === page) &&
                    (!selected || !selected.id || selected.id !== a.id)
            )
            .concat(selected ? [selected] : [])
            .forEach(annotation => {
                const handler = drawingHandlers[annotation.type];
                if (handler) {
                    context.globalAlpha = 1;
                    const isSelected = Boolean(
                        selected && selected.id && selected.id === annotation.id
                    );
                    const toX: ToFunction = x => x * width;
                    const toY: ToFunction = y => y * height;
                    handler.drawAnnotation(
                        {
                            drawContext,
                            annotation,
                            toX,
                            toY,
                        },
                        {
                            selected: isSelected,
                            editable: annotation.editable,
                        }
                    );

                    if (isSelected) {
                        const boundingBox = handler.getBoundingBox({
                            drawContext,
                            annotation,
                            options: handler.toOptions(annotation, {
                                toX,
                                toY,
                            }),
                            toX,
                            toY,
                        });

                        const controls = shapeControlRef.current;
                        const unscale = 1 / drawContext.zoom;
                        if (controls) {
                            controls.setAttribute(
                                'data-editable',
                                annotation.editable ? 'true' : 'false'
                            );
                            (
                                controls.querySelector(
                                    '.edit-controls'
                                ) as HTMLDivElement
                            ).style.display = annotation.editable
                                ? 'inline-block'
                                : 'none';

                            controls.style.transform = `scale(${unscale})`;
                            controls.style.display = 'block';
                            let {offsetWidth, offsetHeight} = controls;
                            offsetWidth *= unscale;
                            offsetHeight *= unscale;

                            const padding = 10 * unscale;

                            let finalX = boundingBox.x;
                            let finalY = boundingBox.y - offsetHeight - padding;
                            if (finalX < 0) {
                                finalX = 0;
                            }
                            if (finalY < 0) {
                                finalY =
                                    boundingBox.y + boundingBox.h + padding;
                            }
                            if (finalY > height - offsetHeight) {
                                finalY = height - offsetHeight;
                            }
                            if (finalX + offsetWidth > width) {
                                finalX = width - offsetWidth;
                            }

                            controls.style.top = finalY + 'px';
                            controls.style.left = finalX + 'px';
                            const selectedAnnotation = annotations.find(
                                a => a.id === selectedAnnotationRef!.current!.id
                            )!;
                            controls.querySelector('.shape-name')!.textContent =
                                selectedAnnotation.name ?? '';
                        }

                        drawRectangle(drawContext, boundingBox, {
                            color: controlsColor,
                            size: unscale,
                        });

                        if (annotation.editable) {
                            drawCircleControl(drawContext, {
                                x: boundingBox.x + boundingBox.w / 2,
                                y: boundingBox.y + boundingBox.h / 2,
                                radius: 0,
                            });
                        }
                    }
                }
            });

        if (!selected) {
            const controls = shapeControlRef.current;
            if (controls) {
                controls.style.display = 'none';
            }
        }
    }
}
