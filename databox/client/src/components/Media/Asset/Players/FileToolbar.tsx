import AssetAnnotationsOverlay, {
    annotationZIndex,
    AssetAnnotationHandle
} from "../Annotations/AssetAnnotationsOverlay.tsx";
import AnnotateWrapper from "../Annotations/AnnotateWrapper.tsx";
import {PropsWithChildren, useRef} from "react";
import {AssetAnnotation, OnNewAnnotation} from "../Annotations/annotationTypes.ts";
import ZoomControls from "./ZoomControls.tsx";
import {TransformComponent, TransformWrapper} from "react-zoom-pan-pinch";
import {Box} from "@mui/material";

type Props = PropsWithChildren<{
    annotationEnabled?: boolean;
    zoomEnabled?: boolean;
    onNewAnnotation?: OnNewAnnotation | undefined;
    annotations?: AssetAnnotation[] | undefined;
}>;

export default function FileToolbar({
    annotations,
    annotationEnabled,
    zoomEnabled,
    onNewAnnotation,
    children,
}: Props) {
    const annotationsOverlayRef = useRef<AssetAnnotationHandle | null>(null);

    return <>
        <AnnotateWrapper
            onNewAnnotation={annotationEnabled ? onNewAnnotation : undefined}
        >
            {({canvas, annotationActive, toolbar}) => <TransformWrapper
                disabled={!zoomEnabled || annotationActive}
                initialScale={1}
                smooth={true}
                disablePadding={true}
                centerOnInit={true}
                centerZoomedOut={false}
            >
                <Box
                    style={{
                        position: 'fixed',
                        bottom: 0,
                        zIndex: annotationZIndex + 1,
                        left: '50%',
                        transform: 'translateX(-50%)',
                        ...(annotationActive ? {
                            pointerEvents: 'none',
                        } : {})
                    }}>
                    <Box sx={{
                        display: 'flex',
                        flexDirection: 'row',
                        alignItems: 'center',
                        p: 2,
                        backgroundColor: `rgba(255, 255, 255, 0.8)`,
                        ...(annotationActive ? {
                            pointerEvents: 'none',
                            opacity: 0.6,
                        } : {})
                    }}>
                        {zoomEnabled && <ZoomControls/>}
                        {toolbar}
                    </Box>
                </Box>
                <TransformComponent
                    wrapperStyle={{
                    width: '100%',
                    height: '100%',
                }}>
                    {canvas}
                    {annotations ? (
                        <AssetAnnotationsOverlay
                            ref={annotationsOverlayRef}
                            annotations={annotations}
                        />
                    ) : null}
                    {children}
                </TransformComponent>
            </TransformWrapper>}
        </AnnotateWrapper>
    </>;
}
