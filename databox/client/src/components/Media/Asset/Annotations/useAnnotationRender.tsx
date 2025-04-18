import React, {useCallback} from 'react';
import type {RenderAnnotationProps} from './renderAnnotation.ts';
import {renderAnnotations} from './renderAnnotation.ts';
import type {ZoomStepState} from '../Players';

type Props = {
    zoomStep: ZoomStepState;
} & RenderAnnotationProps;

export function useAnnotationRender({
    canvasRef,
    shapeControlRef,
    annotations,
    page,
    zoomStep,
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
    }, [
        canvasRef,
        annotations,
        selectedAnnotationRef,
        shapeControlRef,
        zoomStep.current,
        page,
    ]);

    React.useEffect(() => {
        render();
    }, [render]);

    return {
        render,
    };
}
