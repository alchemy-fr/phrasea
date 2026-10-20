import {useCallback, useEffect} from 'react';
import {
    renderAnnotations,
    type RenderAnnotationProps,
} from './renderAnnotation';

type Props = {
    /** Changes when the canvas must be redrawn at another resolution (zoom) */
    renderKey: unknown;
} & RenderAnnotationProps;

/** Redraws the shapes when they, the page or the zoom change. */
export function useAnnotationRender({
    canvasRef,
    shapeControlRef,
    annotations,
    page,
    renderKey,
    zoomRef,
    selectedAnnotationRef,
}: Props) {
    const render = useCallback(() => {
        renderAnnotations({
            canvasRef,
            annotations,
            page,
            zoomRef,
            selectedAnnotationRef,
            shapeControlRef,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        canvasRef,
        annotations,
        selectedAnnotationRef,
        shapeControlRef,
        zoomRef,
        renderKey,
        page,
    ]);

    useEffect(() => {
        render();
    }, [render]);

    // The canvas follows the size of the image (window resize, panel resize)
    useEffect(() => {
        const parent = canvasRef.current?.parentElement;
        if (!parent) {
            return;
        }
        const observer = new ResizeObserver(() => render());
        observer.observe(parent);

        return () => observer.disconnect();
    }, [canvasRef, render]);

    return {render};
}
