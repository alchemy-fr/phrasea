import AssetAnnotationsOverlay, {
    annotationZIndex,
    AssetAnnotationHandle
} from "../Annotations/AssetAnnotationsOverlay.tsx";
import AnnotateWrapper from "../Annotations/AnnotateWrapper.tsx";
import {PropsWithChildren, useCallback, useRef, useState} from "react";
import {AssetAnnotation, OnNewAnnotation} from "../Annotations/annotationTypes.ts";
import ZoomControls from "./ZoomControls.tsx";
import {TransformComponent, TransformWrapper} from "react-zoom-pan-pinch";
import {Box, IconButton, Paper} from "@mui/material";
import CloseIcon from "@mui/icons-material/Close";
import MenuOpenIcon from '@mui/icons-material/MenuOpen';
import {filePlayerRelativeWrapperClassName} from "./index.ts";

type Props = PropsWithChildren<{
    annotationEnabled?: boolean;
    zoomEnabled?: boolean;
    onNewAnnotation?: OnNewAnnotation | undefined;
    annotations?: AssetAnnotation[] | undefined;
    page?: number;
    controls?: boolean | undefined;
    preToolbarActions?: JSX.Element | undefined;
}>;

export default function FileToolbar({
    annotations,
    annotationEnabled,
    zoomEnabled,
    onNewAnnotation,
    children,
    page,
    controls,
    preToolbarActions,
}: Props) {
    const annotationsOverlayRef = useRef<AssetAnnotationHandle | null>(null);
    const [closed, setClosed] = useState(false);
    const contentRef = useRef<HTMLDivElement | null>(null);

    const fitContentToWrapper = useCallback(
        (centerView: (scale: number) => void) => {
            if (contentRef.current) {
                console.log('contentRef', contentRef);
                const wrapperEl = contentRef.current.closest(`.${filePlayerRelativeWrapperClassName}`);
                console.log('wrapperEl', wrapperEl);
                if (wrapperEl) {
                    const wrapperWidth = wrapperEl.clientWidth;
                    const wrapperHeight = wrapperEl.clientHeight;
                    const contentWidth = contentRef.current.clientWidth;
                    const contentHeight = contentRef.current.clientHeight;
                    const widthScale = wrapperWidth / contentWidth;
                    const heightScale = wrapperHeight / contentHeight;
                    const scale = widthScale < heightScale ? widthScale : heightScale;

                    centerView(scale);
                }
            }
        },
        [contentRef]
    );

    return <>
        <AnnotateWrapper
            onNewAnnotation={annotationEnabled ? onNewAnnotation : undefined}
            page={page}
        >
            {({canvas, annotationActive, toolbar}) => <TransformWrapper
                disabled={!controls || !zoomEnabled || annotationActive || closed}
                initialScale={1}
                disablePadding={true}
                centerOnInit={true}
                centerZoomedOut={false}
                minScale={0.1}
            >
                {controls ? <Paper
                    sx={theme => ({
                        borderRadius: theme.shape.borderRadius,
                        position: 'fixed',
                        bottom: theme.spacing(2),
                        zIndex: annotationZIndex + 1,
                        left: !closed ? '50%' : theme.spacing(2),
                        backgroundColor: `rgba(255, 255, 255, 0.8)`,
                        transform: !closed ? 'translateX(-50%)' : undefined,
                        ...(annotationActive ? {
                            pointerEvents: 'none',
                            opacity: 0.6,
                        } : {}),
                        p: 2,
                    })}>
                    <Box sx={{
                        display: 'flex',
                        flexDirection: 'row',
                        alignItems: 'center',
                    }}>
                        {!closed && preToolbarActions}
                        {!closed && zoomEnabled && <ZoomControls
                            fitContentToWrapper={fitContentToWrapper}
                        />}
                        {!closed && toolbar}
                        <IconButton
                            onClick={() => setClosed(p => !p)}
                        >
                            {closed ? <MenuOpenIcon/> : <CloseIcon/>}
                        </IconButton>
                    </Box>
                </Paper> : null}
                <TransformComponent
                    wrapperStyle={{
                    width: '100%',
                    height: '100%',
                    userSelect: 'auto',
                }}>
                    <div ref={contentRef}>
                        {canvas}
                        {annotations ? (
                            <AssetAnnotationsOverlay
                                ref={annotationsOverlayRef}
                                annotations={annotations}
                            />
                        ) : null}
                        {children}
                    </div>
                </TransformComponent>
            </TransformWrapper>}
        </AnnotateWrapper>
    </>;
}
