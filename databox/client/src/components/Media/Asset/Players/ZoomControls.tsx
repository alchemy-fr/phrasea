import {ReactZoomPanPinchHandlers, useControls} from 'react-zoom-pan-pinch';
import ZoomInIcon from '@mui/icons-material/ZoomIn';
import ZoomOutIcon from '@mui/icons-material/ZoomOut';
import {IconButton} from '@mui/material';
import FitScreenIcon from '@mui/icons-material/FitScreen';
import RestartAltIcon from '@mui/icons-material/RestartAlt';
import PanToolIcon from '@mui/icons-material/PanTool';
import {StateSetter} from '../../../../types.ts';
import {useEffect, useRef} from 'react';

type Props = {
    fitContentToWrapper: (
        centerView: ReactZoomPanPinchHandlers['centerView']
    ) => void;
    hand: boolean;
    setHand: StateSetter<boolean>;
    forceHand: boolean | undefined;
    autoCenter?: boolean;
    allowUpscale?: boolean;
};

export default function ZoomControls({
    fitContentToWrapper,
    hand,
    setHand,
    forceHand,
    autoCenter = true,
    allowUpscale = false,
}: Props) {
    const {zoomIn, zoomOut, resetTransform, centerView} = useControls();
    const wasReset = useRef(false);
    const timeoutRef = useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => {
        if (autoCenter && centerView && !wasReset.current) {
            fitContentToWrapper((scale: number | undefined) => {
                if (!scale || (!allowUpscale && scale > 1)) {
                    return;
                }

                centerView(scale);
            });
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
            timeoutRef.current = setTimeout(() => {
                wasReset.current = true;
            }, 500);
        }
    }, [centerView]);

    return (
        <>
            {!forceHand ? (
                <IconButton
                    color={hand ? 'primary' : 'default'}
                    onClick={() => setHand(p => !p)}
                >
                    <PanToolIcon />
                </IconButton>
            ) : null}
            <IconButton
                onClick={() => {
                    wasReset.current = true;
                    zoomIn();
                }}
            >
                <ZoomInIcon />
            </IconButton>
            <IconButton
                onClick={() => {
                    wasReset.current = true;
                    zoomOut();
                }}
            >
                <ZoomOutIcon />
            </IconButton>
            <IconButton
                onClick={() => {
                    wasReset.current = true;
                    resetTransform();
                }}
            >
                <RestartAltIcon />
            </IconButton>
            <IconButton onClick={() => fitContentToWrapper(centerView)}>
                <FitScreenIcon />
            </IconButton>
        </>
    );
}
