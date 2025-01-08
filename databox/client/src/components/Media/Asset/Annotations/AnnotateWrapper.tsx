import {annotationZIndex} from "./AssetAnnotationsOverlay.tsx";
import React, {ReactNode, useRef, useState} from "react";
import {useAnnotationDraw} from "./useAnnotationDraw.ts";
import {AnnotationOptions, AnnotationType, AssetAnnotation, OnNewAnnotation} from "./annotationTypes.ts";
import AnnotateToolbar from "./AnnotateToolbar.tsx";

type Props = {
    onNewAnnotation?: OnNewAnnotation | undefined;
    page?: number,
    children: (props: {
        canvas: ReactNode | null;
        toolbar: ReactNode | null;
        annotationActive: boolean;
    }) => JSX.Element;
};

export default function AnnotateWrapper({
    onNewAnnotation,
    page,
    children,
}: Props) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const [mode, setMode] = useState<AnnotationType | undefined>(undefined);
    const [options, setOptions] = React.useState<AnnotationOptions>({
        color: '#000',
        size: 2,
    });

    useAnnotationDraw({
        canvasRef,
        onNewAnnotation: onNewAnnotation ? (annotation: AssetAnnotation) => {
            onNewAnnotation!({
                ...annotation,
                page,
            });
            setMode(undefined);
        } : undefined,
        mode,
        annotationOptions: options
    });

    return <>
        {children({
            canvas: mode ? <canvas
                ref={canvasRef}
                style={{
                    cursor: 'crosshair',
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    zIndex: annotationZIndex + 1,
                }}
            /> : null,
            toolbar: onNewAnnotation ? <AnnotateToolbar
                options={options}
                setOptions={setOptions}
                mode={mode}
                setMode={setMode}
            /> : null,
            annotationActive: !!mode,
        })}
    </>
}
