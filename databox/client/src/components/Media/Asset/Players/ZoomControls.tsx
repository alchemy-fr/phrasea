import {ReactZoomPanPinchHandlers, useControls} from "react-zoom-pan-pinch";
import ZoomInIcon from '@mui/icons-material/ZoomIn';
import ZoomOutIcon from '@mui/icons-material/ZoomOut';
import {IconButton} from "@mui/material";
import FitScreenIcon from '@mui/icons-material/FitScreen';
import RestartAltIcon from '@mui/icons-material/RestartAlt';

type Props = {
    fitContentToWrapper: (centerView: ReactZoomPanPinchHandlers['centerView']) => void;
};

export default function ZoomControls({fitContentToWrapper}: Props) {
    const {
        zoomIn,
        zoomOut,
        resetTransform,
        centerView,
    } = useControls();

    return <>
        <IconButton onClick={() => zoomIn()}>
            <ZoomInIcon/>
        </IconButton>
        <IconButton onClick={() => zoomOut()}>
            <ZoomOutIcon/>
        </IconButton>
        <IconButton onClick={() => resetTransform()}>
            <RestartAltIcon/>
        </IconButton>
        <IconButton onClick={() => fitContentToWrapper(centerView)}>
            <FitScreenIcon/>
        </IconButton>
    </>
}
