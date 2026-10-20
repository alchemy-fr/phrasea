import {getDrawingHandler} from './handlers';
import type {ToFunction} from './handlerTypes';
import type {
    AssetAnnotation,
    CanvasRef,
    SelectedAnnotationRef,
    ShapeControlRef,
    ZoomRef,
} from './types';
import {getZoomFromRef} from './common';
import {drawRectangle} from './shapes/rectangle';
import {controlsColor} from './controls';
import {drawCircleControl} from './shapes/circle';

export type RenderAnnotationProps = {
    canvasRef: CanvasRef;
    annotations: AssetAnnotation[];
    page?: number;
    selectedAnnotationRef?: SelectedAnnotationRef;
    zoomRef: ZoomRef;
    shapeControlRef: ShapeControlRef;
};

/**
 * Draws every shape of the page on the canvas (sized to its parent), the
 * selected one last with its bounding box, and places the shape control
 * panel next to it.
 */
export function renderAnnotations({
    canvasRef,
    annotations,
    page,
    selectedAnnotationRef,
    zoomRef,
    shapeControlRef,
}: RenderAnnotationProps) {
    const canvas = canvasRef.current;
    if (!canvas) {
        return;
    }
    const parent = canvas.parentNode as HTMLElement;
    const {offsetWidth: width, offsetHeight: height} = parent;
    if (!width || !height) {
        return;
    }

    // Sharp at the current zoom (bounded: a 16x zoom would need a huge bitmap)
    const resolution =
        Math.min(devicePixelRatio, 4) * Math.min(zoomRef.current || 1, 3);
    canvas.width = width * resolution;
    canvas.height = height * resolution;
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';

    const context = canvas.getContext('2d')!;
    context.scale(resolution, resolution);

    let selected = selectedAnnotationRef?.current;
    if (selected && !annotations.find(a => a.id === selected!.id)) {
        selected = undefined;
    }

    const drawContext = {
        context,
        zoom: getZoomFromRef(zoomRef),
    };
    const toX: ToFunction = x => x * width;
    const toY: ToFunction = y => y * height;

    annotations
        .filter(
            a =>
                (!page || !a.page || a.page === page) &&
                (!selected || !selected.id || selected.id !== a.id)
        )
        .concat(selected ? [selected] : [])
        .forEach(annotation => {
            const handler = getDrawingHandler(annotation.type);
            if (!handler) {
                return;
            }
            context.globalAlpha = 1;
            const isSelected = Boolean(
                selected && selected.id && selected.id === annotation.id
            );
            handler.drawAnnotation(
                {drawContext, annotation, toX, toY},
                {selected: isSelected, editable: annotation.editable}
            );

            if (!isSelected) {
                return;
            }

            const boundingBox = handler.getBoundingBox({
                drawContext,
                annotation,
                options: handler.toOptions(annotation, {toX, toY}),
                toX,
                toY,
            });

            const unscale = 1 / drawContext.zoom;
            const controls = shapeControlRef.current;
            if (controls) {
                controls.setAttribute(
                    'data-editable',
                    annotation.editable ? 'true' : 'false'
                );
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
                    finalY = boundingBox.y + boundingBox.h + padding;
                }
                if (finalY > height - offsetHeight) {
                    finalY = height - offsetHeight;
                }
                if (finalX + offsetWidth > width) {
                    finalX = width - offsetWidth;
                }

                controls.style.top = finalY + 'px';
                controls.style.left = finalX + 'px';
                const name = controls.querySelector('[data-shape-name]');
                if (name) {
                    name.textContent = annotation.name ?? '';
                }
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
        });

    if (!selected && shapeControlRef.current) {
        shapeControlRef.current.style.display = 'none';
    }
}
