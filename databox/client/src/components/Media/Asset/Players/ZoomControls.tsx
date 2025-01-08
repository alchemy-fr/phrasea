import {useControls} from "react-zoom-pan-pinch";
import ZoomInIcon from '@mui/icons-material/ZoomIn';
import ZoomOutIcon from '@mui/icons-material/ZoomOut';
import {IconButton} from "@mui/material";
import FitScreenIcon from '@mui/icons-material/FitScreen';

type Props = {};

export default function ZoomControls({}: Props) {
    const {
        zoomIn,
        zoomOut,
        resetTransform
    } = useControls();

    return <>
        <IconButton onClick={() => zoomIn()}>
            <ZoomInIcon/>
        </IconButton>
        <IconButton onClick={() => zoomOut()}>
            <ZoomOutIcon/>
        </IconButton>
        <IconButton onClick={() => resetTransform()}>
            <FitScreenIcon/>
        </IconButton>
    </>
}
