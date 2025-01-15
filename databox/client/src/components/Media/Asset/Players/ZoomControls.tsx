import {ReactZoomPanPinchHandlers, useControls} from 'react-zoom-pan-pinch';
import ZoomInIcon from '@mui/icons-material/ZoomIn';
import ZoomOutIcon from '@mui/icons-material/ZoomOut';
import {IconButton} from '@mui/material';
import FitScreenIcon from '@mui/icons-material/FitScreen';
import RestartAltIcon from '@mui/icons-material/RestartAlt';
import PanToolIcon from '@mui/icons-material/PanTool';
import {StateSetter} from '../../../../types.ts';

type Props = {
    fitContentToWrapper: (
        centerView: ReactZoomPanPinchHandlers['centerView']
    ) => void;
    hand: boolean;
    setHand: StateSetter<boolean>;
    forceHand: boolean | undefined;
};

export default function ZoomControls({
    fitContentToWrapper,
    hand,
    setHand,
    forceHand,
}: Props) {
    const {zoomIn, zoomOut, resetTransform, centerView} = useControls();

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
            <IconButton onClick={() => zoomIn()}>
                <ZoomInIcon />
            </IconButton>
            <IconButton onClick={() => zoomOut()}>
                <ZoomOutIcon />
            </IconButton>
            <IconButton onClick={() => resetTransform()}>
                <RestartAltIcon />
            </IconButton>
            <IconButton onClick={() => fitContentToWrapper(centerView)}>
                <FitScreenIcon />
            </IconButton>
        </>
    );
}
