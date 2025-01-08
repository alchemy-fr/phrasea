import React from 'react';
import {AssetAnnotation} from "./annotationTypes.ts";
import {drawingHandlers} from "./events.ts";

type Props = {
    annotations: AssetAnnotation[];
};

export const annotationZIndex = 100;

export default function AssetAnnotationsOverlay({annotations}: Props) {
    const canvasRef = React.useRef<HTMLCanvasElement | null>(null);

    React.useEffect(() => {
        if (canvasRef.current) {
            const canvas = canvasRef.current;
            const parent = canvas.parentNode as HTMLDivElement;
            const parentRect = parent.getBoundingClientRect();
            const {width, height} = parentRect;

            const resolution = Math.max(devicePixelRatio, 2);
            canvas.width = width * resolution;
            canvas.height = height * resolution;
            canvas.style.width = width + "px";
            canvas.style.height = height + "px";

            const context = canvas!.getContext('2d')!;
            context.scale(resolution, resolution);

            annotations.forEach(annotation => {
                const handler = drawingHandlers[annotation.type];
                if (handler) {
                    handler.drawAnnotation({
                        context,
                        annotation,
                    });
                }
            });
        }
    }, [canvasRef, annotations]);

    return <canvas
        ref={canvasRef}
        style={{
            position: 'absolute',
            top: 0,
            left: 0,
            zIndex: annotationZIndex,
            width: '100%',
            height: '100%',
        }}
    />
}
