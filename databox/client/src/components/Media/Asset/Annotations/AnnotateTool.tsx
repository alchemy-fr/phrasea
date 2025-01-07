import {AnnotationType, OnNewAnnotation} from "../../../../types.ts";
import {annotationZIndex} from "./AssetAnnotationsOverlay.tsx";
import {Box} from "@mui/material";

type Props = {
    onNewAnnotation: OnNewAnnotation;
    page?: number,
};

export default function AnnotateTool({
    onNewAnnotation,
    page,
}: Props) {

    return <Box
        sx={{
            position: 'absolute',
            top: 0,
            left: 0,
            zIndex: annotationZIndex + 1,
            backgroundColor: 'background.paper',
            p: 2,
        }}
        onClick={() => {
        onNewAnnotation({
            page,
            x: 15,
            y: 15,
            type: AnnotationType.Point,
        })
    }}>
        AnnotateTool
    </Box>
}
