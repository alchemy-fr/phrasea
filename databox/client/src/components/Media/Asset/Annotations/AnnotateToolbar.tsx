import {IconButton, TextField} from '@mui/material';
import {AnnotationOptions, AnnotationType} from './annotationTypes.ts';
import MyLocationIcon from '@mui/icons-material/MyLocation';
import Crop32Icon from '@mui/icons-material/Crop32';
import PanoramaFishEyeIcon from '@mui/icons-material/PanoramaFishEye';
import GestureIcon from '@mui/icons-material/Gesture';
import {ColorPicker} from '@alchemy/react-form';
import {StateSetter} from '../../../../types.ts';
import ToolbarPaper from '../Players/ToolbarPaper.tsx';
import BrushIcon from '@mui/icons-material/Brush';

type Props = {
    mode: AnnotationType | undefined;
    setMode: StateSetter<AnnotationType | undefined>;
    options: AnnotationOptions;
    setOptions: StateSetter<AnnotationOptions>;
    annotate: boolean;
    setAnnotate: StateSetter<boolean>;
};

export default function AnnotateToolbar({
    mode,
    setMode,
    options,
    setOptions,
    annotate,
    setAnnotate,
}: Props) {
    return (
        <>
            <IconButton
                title={'Annotate'}
                color={annotate ? 'primary' : 'default'}
                onClick={() => setAnnotate(p => !p)}
            >
                <GestureIcon />
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
                                mode === AnnotationType.Point
                                    ? 'primary'
                                    : 'default'
                            }
                            onClick={() => setMode(AnnotationType.Point)}
                        >
                            <MyLocationIcon />
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
                            <Crop32Icon />
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
                            <PanoramaFishEyeIcon />
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
                            <BrushIcon />
                        </IconButton>
                    </div>
                    <div>
                        <ColorPicker
                            displayField={false}
                            color={options.color}
                            onChange={c => {
                                setOptions(p => ({...p, color: c}));
                            }}
                        />
                    </div>
                    <div>
                        <TextField
                            label={'Size'}
                            type={'number'}
                            style={{width: 100}}
                            value={options.size}
                            onChange={e =>
                                setOptions(p => ({
                                    ...p,
                                    size: parseInt(e.target.value),
                                }))
                            }
                        />
                    </div>
                </ToolbarPaper>
            )}
        </>
    );
}
