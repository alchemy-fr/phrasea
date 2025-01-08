import {Box, IconButton, TextField} from "@mui/material";
import {AnnotationOptions, AnnotationType} from "./annotationTypes.ts";
import MyLocationIcon from "@mui/icons-material/MyLocation";
import Crop32Icon from "@mui/icons-material/Crop32";
import PanoramaFishEyeIcon from "@mui/icons-material/PanoramaFishEye";
import DrawIcon from "@mui/icons-material/Draw";
import {ColorPicker} from "../../../../../../../lib/js/react-form";
import {StateSetter} from "../../../../types.ts";

type Props = {
    mode: AnnotationType | undefined;
    setMode: StateSetter<AnnotationType | undefined>;
    options: AnnotationOptions;
    setOptions: StateSetter<AnnotationOptions>;
};

export default function AnnotateToolbar({
    mode,
    setMode,
    options,
    setOptions,
}: Props) {
    return <>
        <div>
            <IconButton
                disabled={mode === AnnotationType.Point}
                onClick={() => setMode(AnnotationType.Point)}
            >
                <MyLocationIcon/>
            </IconButton>
        </div>
        <div>
            <IconButton
                disabled={mode === AnnotationType.Rect}
                onClick={() => setMode(AnnotationType.Rect)}
            >
                <Crop32Icon/>
            </IconButton>
        </div>
        <div>
            <IconButton
                disabled={mode === AnnotationType.Circle}
                onClick={() => setMode(AnnotationType.Circle)}
            >
                <PanoramaFishEyeIcon/>
            </IconButton>
        </div>
        <div>
            <IconButton
                disabled={mode === AnnotationType.Draw}
                onClick={() => setMode(AnnotationType.Draw)}
            >
                <DrawIcon/>
            </IconButton>
        </div>
        <div>
            <ColorPicker color={options.color} onChange={(c) => {
                setOptions(p => ({...p, color: c}));
            }}/>
        </div>
        <div>
            <TextField
                label={'Size'}
                type={'number'}
                value={options.size}
                onChange={(e) => setOptions(p => ({...p, size: parseInt(e.target.value)}))}
            />
        </div>
    </>
}
