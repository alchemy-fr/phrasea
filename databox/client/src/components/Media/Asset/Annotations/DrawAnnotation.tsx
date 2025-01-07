import {DrawAnnotation as TDrawAnnotation} from "./annotationTypes.ts";
import React from "react";

type Props = {} & TDrawAnnotation;

export default function DrawAnnotation({
    paths,
    s = 2,
    c = '#000',
}: Props) {
    const canvasRef = React.useRef<HTMLCanvasElement | null>(null);
    React.useEffect(() => {
        if (canvasRef.current) {
            const canvas = canvasRef.current;
            const parent = canvas.parentNode as HTMLDivElement;
            const parentRect = parent.getBoundingClientRect();
            const {width, height} = parentRect;

            var resolution = Math.max(devicePixelRatio, 2);
            canvas.width = width * resolution;
            canvas.height = height * resolution;

            canvas.style.width = '100%';
            canvas.style.height = '100%';

            const context = canvas!.getContext('2d')!;
            context.scale(resolution, resolution);

            context.lineCap = 'round';
            context.lineJoin = 'round';
            context.lineWidth = s!;
            context.strokeStyle = c!;

            context.beginPath();
            paths.forEach((point, i) => {
                if (i === 0) {
                    context.moveTo(point.x * width, point.y * height);
                } else {
                    context.lineTo(point.x * width, point.y * height);
                }
            });
            context.stroke();
        }
    }, [canvasRef, paths, s, c]);

    return (
        <canvas
            ref={canvasRef}
            data-type={'draw'}
            style={{
                position: 'absolute',
                top: 0,
                left: 0,
                width: `100%`,
                height: `100%`,
            }}
        />
    );
}
