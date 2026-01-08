import AnnotateWrapper, {
    BaseAnnotationProps,
} from '../Annotations/AnnotateWrapper.tsx';
import {useCallback, useEffect, useRef, useState} from 'react';
import {AssetAnnotationRef} from '../Annotations/annotationTypes.ts';
import ZoomControls from './ZoomControls.tsx';
import {
    ReactZoomPanPinchContentRef,
    ReactZoomPanPinchHandlers,
    TransformComponent,
    TransformWrapper,
} from 'react-zoom-pan-pinch';
import {Box, IconButton} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import MenuOpenIcon from '@mui/icons-material/MenuOpen';
import {
    filePlayerRelativeWrapperClassName,
    ZoomPanPinchContentRef,
    ZoomStepState,
} from './index.ts';
import ToolbarPaper from './ToolbarPaper.tsx';
import {useMatomo} from '@jonkoops/matomo-tracker-react';

type Props = {
    annotationEnabled?: boolean;
    zoomEnabled?: boolean;
    assetAnnotationsRef?: AssetAnnotationRef;
    page?: number;
    controls?: boolean | undefined;
    preToolbarActions?: JSX.Element | undefined;
    forceHand?: boolean;
    trackingId?: string;
    children:
        | ((props: {
              zoomStep: ZoomStepState;
              transformWrapperRef: ZoomPanPinchContentRef;
          }) => JSX.Element)
        | JSX.Element;
} & BaseAnnotationProps;

export default function FileToolbar({
    annotations,
    annotationEnabled,
    zoomEnabled,
    assetAnnotationsRef,
    children,
    page,
    controls,
    preToolbarActions,
    forceHand,
    ...annotateProps
}: Props) {
    const assetAnnotationsRefFallback: AssetAnnotationRef = useRef(null);
    const finalAssetAnnotationsRef =
        assetAnnotationsRef ?? assetAnnotationsRefFallback;
    const transformWrapperRef = useRef<ReactZoomPanPinchContentRef | null>(
        null
    );

    const {pushInstruction} = useMatomo();
    const zoomRef = useRef<number>(1);
    const [closed, setClosed] = useState(false);
    const [hand, setHand] = useState(forceHand ?? false);
    const contentRef = useRef<HTMLDivElement | null>(null);
    const [zoomStep, setZoomStep] = useState<ZoomStepState>({
        current: 1,
        maxReached: 1,
    });

    const increaseZoomStep = useCallback(
        (step: number): void => {
            setZoomStep(p => {
                const current =
                    step < 1
                        ? Math.ceil(step * 10) / 10
                        : Math.min(Math.ceil(step), 10);
                if (current === p.current) {
                    return p;
                }

                return {
                    current,
                    maxReached: Math.max(p.maxReached, current),
                };
            });
        },
        [setZoomStep]
    );

    useEffect(() => {
        setZoomStep({
            current: 1,
            maxReached: 1,
        });
    }, [contentRef]);

    const fitContentToWrapper = useCallback(
        (centerView: ReactZoomPanPinchHandlers['centerView']) => {
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
                {...annotateProps}
                annotationEnabled={annotationEnabled}
                annotations={annotations}
                page={page}
                ref={finalAssetAnnotationsRef}
                zoomStep={zoomStep}
                zoomRef={zoomRef}
            >
                {({canvas, annotationActive, toolbar}) => {
                    const disabled =
                        !controls || !zoomEnabled || closed || !hand;

                    return (
                        <TransformWrapper
                            ref={transformWrapperRef}
                            disabled={disabled}
                            initialScale={1}
                            disablePadding={true}
                            centerOnInit={true}
                            centerZoomedOut={false}
                            minScale={0.1}
                            maxScale={100}
                            onTransformed={(_ref, {scale}) => {
                                increaseZoomStep(scale);
                                zoomRef.current = scale;
                            }}
                        >
                            {controls ? (
                                <ToolbarPaper
                                    annotationActive={annotationActive}
                                    sx={theme => ({
                                        bottom: theme.spacing(2),
                                        left: !closed
                                            ? '50%'
                                            : theme.spacing(2),
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
                                                <MenuOpenIcon />
                                            ) : (
                                                <CloseIcon />
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
                                    onClick={() => {
                                        if (
                                            typeof children !== 'function' &&
                                            annotateProps.trackingId
                                        ) {
                                            pushInstruction(
                                                'trackContentInteraction',
                                                'click',
                                                annotateProps.trackingId,
                                                children.props.src,
                                                ''
                                            );
                                        }
                                    }}
                                >
                                    {canvas}
                                    {typeof children === 'function'
                                        ? children({
                                              zoomStep,
                                              transformWrapperRef,
                                          })
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
