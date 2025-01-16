import AnnotateWrapper, {
    AssetAnnotationHandle,
} from '../Annotations/AnnotateWrapper.tsx';
import {MutableRefObject, useCallback, useRef, useState} from 'react';
import {
    AnnotationsControl,
    AssetAnnotation,
} from '../Annotations/annotationTypes.ts';
import ZoomControls from './ZoomControls.tsx';
import {TransformComponent, TransformWrapper} from 'react-zoom-pan-pinch';
import {Box, IconButton} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import MenuOpenIcon from '@mui/icons-material/MenuOpen';
import {filePlayerRelativeWrapperClassName} from './index.ts';
import ToolbarPaper from './ToolbarPaper.tsx';

type Props = {
    annotationEnabled?: boolean;
    zoomEnabled?: boolean;
    annotationsControl?: AnnotationsControl | undefined;
    annotations?: AssetAnnotation[] | undefined;
    page?: number;
    controls?: boolean | undefined;
    preToolbarActions?: JSX.Element | undefined;
    forceHand?: boolean;
    children:
        | ((props: {
              annotationsWrapperRef: MutableRefObject<AssetAnnotationHandle | null>;
          }) => JSX.Element)
        | JSX.Element;
};

export default function FileToolbar({
    annotations,
    annotationEnabled,
    zoomEnabled,
    annotationsControl,
    children,
    page,
    controls,
    preToolbarActions,
    forceHand,
}: Props) {
    const annotationsWrapperRef = useRef<AssetAnnotationHandle | null>(null);
    const [closed, setClosed] = useState(false);
    const [hand, setHand] = useState(forceHand ?? false);
    const contentRef = useRef<HTMLDivElement | null>(null);

    const fitContentToWrapper = useCallback(
        (centerView: (scale: number) => void) => {
            if (contentRef.current) {
                const wrapperEl = contentRef.current.closest(
                    `.${filePlayerRelativeWrapperClassName}`
                );
                if (wrapperEl) {
                    const widthScale =
                        wrapperEl.clientWidth / contentRef.current.clientWidth;
                    const heightScale =
                        wrapperEl.clientHeight /
                        contentRef.current.clientHeight;
                    const scale =
                        widthScale < heightScale ? widthScale : heightScale;

                    centerView(scale);
                }
            }
        },
        [contentRef]
    );

    return (
        <>
            <AnnotateWrapper
                annotationEnabled={annotationEnabled}
                annotations={annotations}
                annotationsControl={annotationsControl}
                page={page}
                ref={annotationsWrapperRef}

            >
                {({canvas, annotationActive, annotate, toolbar}) => {
                    const disabled = !controls ||
                        !zoomEnabled ||
                        annotationActive ||
                        annotate ||
                        closed ||
                        !hand;
                    return (
                        <TransformWrapper
                            disabled={disabled}
                            initialScale={1}
                            disablePadding={true}
                            centerOnInit={true}
                            centerZoomedOut={false}
                            minScale={0.1}
                        >
                            {controls ? (
                                <ToolbarPaper
                                    annotationActive={annotationActive}
                                    sx={theme => ({
                                        bottom: theme.spacing(2),
                                        left: !closed ? '50%' : theme.spacing(2),
                                        transform: !closed
                                            ? 'translateX(-50%)'
                                            : undefined,
                                    })}
                                >
                                    <Box
                                        sx={{
                                            display: 'flex',
                                            flexDirection: 'row',
                                            alignItems: 'center',
                                        }}
                                    >
                                        {!closed && preToolbarActions}
                                        {!closed && zoomEnabled && (
                                            <ZoomControls
                                                fitContentToWrapper={
                                                    fitContentToWrapper
                                                }
                                                setHand={setHand}
                                                hand={hand}
                                                forceHand={forceHand}
                                            />
                                        )}
                                        {!closed && toolbar}
                                        <IconButton
                                            onClick={() => setClosed(p => !p)}
                                        >
                                            {closed ? (
                                                <MenuOpenIcon/>
                                            ) : (
                                                <CloseIcon/>
                                            )}
                                        </IconButton>
                                    </Box>
                                </ToolbarPaper>
                            ) : null}
                            <TransformComponent
                                wrapperStyle={{
                                    width: '100%',
                                    height: '100%',
                                    userSelect: 'auto',
                                }}
                            >
                                <div
                                    ref={contentRef}
                                    style={{
                                        cursor: !disabled ? 'grab' : 'auto',
                                    }}
                                >
                                    {canvas}
                                    {typeof children === 'function'
                                        ? children({annotationsWrapperRef})
                                        : children}
                                </div>
                            </TransformComponent>
                        </TransformWrapper>
                    );
                }}
            </AnnotateWrapper>
        </>
    );
}
