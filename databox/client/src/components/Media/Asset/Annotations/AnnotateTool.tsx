import {annotationZIndex} from "./AssetAnnotationsOverlay.tsx";
import {Box, IconButton, TextField} from "@mui/material";
import React, {PropsWithChildren, useRef, useState} from "react";
import {useAnnotationDraw} from "./useAnnotationDraw.ts";
import {AnnotationType, AssetAnnotation, OnNewAnnotation} from "./annotationTypes.ts";
import DrawIcon from '@mui/icons-material/Draw';
import Crop32Icon from '@mui/icons-material/Crop32';
import MyLocationIcon from '@mui/icons-material/MyLocation';
import PanoramaFishEyeIcon from '@mui/icons-material/PanoramaFishEye';
import {ColorPicker} from "../../../../../../../lib/js/react-form";

type Props = PropsWithChildren<{
    onNewAnnotation?: OnNewAnnotation | undefined;
    page?: number,
}>;

export default function AnnotateTool({
    onNewAnnotation,
    page,
    children,
}: Props) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const [mode, setMode] = useState<AnnotationType | undefined>(undefined);
    const [size, setSize] = React.useState<number>(2);
    const [color, setColor] = React.useState<string>('#000');

    useAnnotationDraw({
        canvasRef,
        onNewAnnotation: onNewAnnotation ? (annotation: AssetAnnotation) => {
            onNewAnnotation!({
                ...annotation,
                page,
            });
            setMode(undefined);
        } : undefined,
        mode,
        annotationOptions: {
            color,
            size,
        }
    });

    return <div
        style={{
            position: 'relative',
        }}
    >
        {!!onNewAnnotation && <Box
            sx={{
                position: 'sticky',
                display: 'flex',
                flexDirection: 'row',
                alignItems: 'center',
                top: 0,
                left: 0,
                zIndex: annotationZIndex + 1,
                backgroundColor: 'background.paper',
                p: 2,
            }}
        >
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
                <ColorPicker color={color} onChange={setColor}/>
            </div>
            <div>
                <TextField
                    label={'Size'}
                    type={'number'}
                    value={size}
                    onChange={(e) => setSize(parseInt(e.target.value))}
                />
            </div>
        </Box>}
        <>
            {children}
            {mode && <canvas
                ref={canvasRef}
                style={{
                    cursor: 'crosshair',
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    zIndex: annotationZIndex,
                }}
            />}
        </>
    </div>
}
