import {IconButton, TextField} from '@mui/material';
import {AnnotationOptions, AnnotationsControl, AnnotationType, SelectedAnnotationRef} from './annotationTypes.ts';
import MyLocationIcon from '@mui/icons-material/MyLocation';
import Crop32Icon from '@mui/icons-material/Crop32';
import PanoramaFishEyeIcon from '@mui/icons-material/PanoramaFishEye';
import HorizontalRuleIcon from '@mui/icons-material/HorizontalRule';
import GestureIcon from '@mui/icons-material/Gesture';
import {ColorPicker} from '@alchemy/react-form';
import {StateSetter} from '../../../../types.ts';
import ToolbarPaper from '../Players/ToolbarPaper.tsx';
import BrushIcon from '@mui/icons-material/Brush';
import {drawingHandlers} from "./events.ts";
import {MutableRefObject} from "react";
import ArrowRightAltIcon from '@mui/icons-material/ArrowRightAlt';
import AbcIcon from '@mui/icons-material/Abc';

function changeIfSelected(
    canvasRef: MutableRefObject<HTMLCanvasElement | null>,
    annotationsControl: AnnotationsControl | undefined,
    selectedAnnotationRef: SelectedAnnotationRef,
    options: AnnotationOptions,
): AnnotationOptions {
    if (annotationsControl) {
        const annotation = selectedAnnotationRef.current;
        if (annotation && annotation!.id) {
            const id = annotation.id;
            const handler = drawingHandlers[annotation.type];
            if (handler) {
                setTimeout(() => {
                    const newAnnotation = handler.fromOptions(options, annotation, {
                        relativeX: x => x / canvasRef.current!.offsetWidth,
                        relativeY: y => y / canvasRef.current!.offsetHeight,
                    });
                    annotationsControl?.onUpdate(id, newAnnotation);
                    selectedAnnotationRef.current = newAnnotation;
                }, 0);
            }
        }
    }

    return options;
}

type Props = {
    mode: AnnotationType | undefined;
    setMode: StateSetter<AnnotationType | undefined>;
    options: AnnotationOptions;
    setOptions: StateSetter<AnnotationOptions>;
    annotate: boolean;
    setAnnotate: StateSetter<boolean>;
    annotationsControl: AnnotationsControl | undefined;
    selectedAnnotationRef: SelectedAnnotationRef;
    canvasRef: MutableRefObject<HTMLCanvasElement | null>;
};

export default function AnnotateToolbar({
    mode,
    setMode,
    options,
    setOptions,
    annotate,
    setAnnotate,
    annotationsControl,
    selectedAnnotationRef,
    canvasRef,
}: Props) {
    return (
        <>
            <IconButton
                title={'Annotate'}
                color={annotate ? 'primary' : 'default'}
                onClick={() => setAnnotate(p => !p)}
            >
                <GestureIcon/>
            </IconButton>
            {annotate && (
                <ToolbarPaper
                    annotationActive={!!mode}
                    sx={{
                        bottom: 80,
                        display: 'flex',
                        flexDirection: 'row',
                    }}
                >
                    <div>
                        <IconButton
                            color={
                                mode === AnnotationType.Text
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Text)}
                        >
                            <AbcIcon/>
                        </IconButton>
                    </div>
                    <div>
                        <IconButton
                            color={
                                mode === AnnotationType.Point
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Point)}
                        >
                            <MyLocationIcon/>
                        </IconButton>
                    </div>
                    <div>
                        <IconButton
                            color={
                                mode === AnnotationType.Rect
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Rect)}
                        >
                            <Crop32Icon/>
                        </IconButton>
                    </div>
                    <div>
                        <IconButton
                            color={
                                mode === AnnotationType.Circle
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Circle)}
                        >
                            <PanoramaFishEyeIcon/>
                        </IconButton>
                    </div>
                    <div>
                        <IconButton
                            color={
                                mode === AnnotationType.Arrow
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Arrow)}
                        >
                            <ArrowRightAltIcon/>
                        </IconButton>
                    </div>
                    <div>
                        <IconButton
                            color={
                                mode === AnnotationType.Line
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Line)}
                        >
                            <HorizontalRuleIcon/>
                        </IconButton>
                    </div>
                    <div>
                        <IconButton
                            color={
                                mode === AnnotationType.Draw
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Draw)}
                        >
                            <BrushIcon/>
                        </IconButton>
                    </div>
                    <div>
                        <ColorPicker
                            displayField={false}
                            color={options.color}
                            onChange={c => {
                                setOptions(p =>
                                    changeIfSelected(
                                        canvasRef,
                                        annotationsControl,
                                        selectedAnnotationRef,
                                        {...p, color: c}
                                    ));
                            }}
                        />
                    </div>
                    <div>
                        <TextField
                            label={'Size'}
                            type={'number'}
                            inputProps={{
                                step: options.size <= 1 ? 0.1 : 1,
                            }}
                            style={{width: 100}}
                            value={options.size}
                            onChange={e => {
                                const size = Math.max(0.001, parseFloat(e.target.value) || 1);

                                return setOptions(p =>
                                    changeIfSelected(
                                        canvasRef,
                                        annotationsControl,
                                        selectedAnnotationRef,
                                        {
                                            ...p,
                                            size,
                                        }
                                    ));
                            }
                            }
                        />
                    </div>
                </ToolbarPaper>
            )}
        </>
    );
}
