import React, {forwardRef, memo, useCallback, useImperativeHandle} from 'react';
import {AssetAnnotation} from './annotationTypes.ts';
import {drawingHandlers} from './events.ts';

type Props = {
    annotations: AssetAnnotation[];
};

export const annotationZIndex = 100;

export type AssetAnnotationHandle = {
    render: () => void;
};

const AssetAnnotationsOverlay = memo(
    forwardRef<AssetAnnotationHandle, Props>(function AssetAnnotationsOverlay(
        {annotations},
        ref
    ) {
        const canvasRef = React.useRef<HTMLCanvasElement | null>(null);

        const render = useCallback(() => {
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

                annotations.forEach(annotation => {
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
        }, [canvasRef, annotations]);

        React.useEffect(() => {
            render();
        }, [render]);

        useImperativeHandle(ref, () => {
            return {
                render,
            };
        }, [render]);

        return (
            <canvas
                ref={canvasRef}
                className={'annotation-overlay'}
                style={{
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    zIndex: annotationZIndex,
                    pointerEvents: 'none',
                }}
            />
        );
    })
);

export default AssetAnnotationsOverlay;
