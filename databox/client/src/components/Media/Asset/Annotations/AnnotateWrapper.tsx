import {annotationZIndex} from "./AssetAnnotationsOverlay.tsx";
import React, {PropsWithChildren, useRef, useState} from "react";
import {useAnnotationDraw} from "./useAnnotationDraw.ts";
import {AnnotationOptions, AnnotationType, AssetAnnotation, OnNewAnnotation} from "./annotationTypes.ts";
import AnnotateToolbar from "./AnnotateToolbar.tsx";

type Props = PropsWithChildren<{
    onNewAnnotation?: OnNewAnnotation | undefined;
    page?: number,
}>;

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
        {!!onNewAnnotation && <div
            style={{
                position: 'fixed',
                bottom: 0,
                zIndex: annotationZIndex + 1,
                left: '50%',
                transform: 'translateX(-50%)',
                ...(mode ? {
                    pointerEvents: 'none',
                } : {})
            }}
        >
            <AnnotateToolbar
                options={options}
                setOptions={setOptions}
                mode={mode}
                setMode={setMode}
            />
        </div>}
        <div
            style={{
                position: 'relative',
                paddingBottom: 90,
            }}
        >
            <>
                {children}
                {mode && <canvas
                    ref={canvasRef}
                    style={{
                        cursor: 'crosshair',
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        zIndex: annotationZIndex,
                    }}
                />}
            </>
        </div>
    </>
}
