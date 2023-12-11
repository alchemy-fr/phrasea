import {MouseEvent, useContext, useState} from 'react';
import {Dimensions, PlayerProps} from './index';
import ReactPlayer from 'react-player';
import {Box, IconButton, LinearProgress} from '@mui/material';
import {DisplayContext} from '../../DisplayContext';
import PlayCircleIcon from '@mui/icons-material/PlayCircle';
import PauseIcon from '@mui/icons-material/Pause';
import {getSizeCase} from '../../../../lib/sizeCase';
import {FileTypeEnum, getFileTypeFromMIMEType} from '../../../../lib/file';

type Progress = {
    played: number;
    loaded: number;
};

const playerActionsClass = 'pa';

export function getMaxVideoDimensions(
    maxDimensions: Dimensions,
    ratio: number | undefined
): Dimensions {
    if (!ratio) {
        return maxDimensions;
    }

    if (maxDimensions.width * ratio > maxDimensions.height) {
        return {
            width: maxDimensions.height / ratio,
            height: maxDimensions.height,
        };
    }

    return {
        width: maxDimensions.width,
        height: maxDimensions.width * ratio,
    };
}

function stopPropagationIfNoCtrl(e: MouseEvent) {
    if (!e.ctrlKey) {
        e.stopPropagation();
    }
}

type Props = {
    autoPlayable: boolean;
    controls?: boolean | undefined;
} & PlayerProps;

export default function VideoPlayer({
    file,
    minDimensions,
    maxDimensions,
    onLoad,
    autoPlayable,
    noInteraction,
    controls,
}: Props) {
    const [progress, setProgress] = useState<Progress>();
    const [duration, setDuration] = useState<number>();
    const displayContext = useContext(DisplayContext);
    const [play, setPlay] = useState(false);
    const [ratio, setRatio] = useState<number>();
    const type = getFileTypeFromMIMEType(file.type);
    const isAudio = type === FileTypeEnum.Audio;
    const videoDimensions = getMaxVideoDimensions(maxDimensions, ratio);
    const autoPlay = autoPlayable && displayContext?.playVideos;

    const onPlay = (e: MouseEvent) => {
        if (e.ctrlKey) {
            return;
        }
        e.stopPropagation();
        setPlay(p => {
            displayContext?.setPlaying({
                stop: !p ? () => setPlay(false) : () => {},
            });

            return !p;
        });
    };

    const PlayComponent = play ? PauseIcon : PlayCircleIcon;

    const hasControls = !noInteraction && controls;

    return (
        <Box
            sx={theme => ({
                'position': 'relative',
                'backgroundColor': isAudio ? '#FFF' : '#000',
                'display': 'flex',
                'justifyContent': 'center',
                'alignItems': 'center',
                'minWidth': minDimensions?.width,
                'minHeight': minDimensions?.height,
                'pointerEvents': hasControls ? 'auto' : undefined,
                [`.${playerActionsClass}`]: {
                    'pointerEvents': 'none',
                    'display': 'flex',
                    'flexDirection': 'column',
                    'justifyContent': 'center',
                    'alignItems': 'center',
                    'position': 'absolute',
                    'top': 0,
                    'left': 0,
                    'right': 0,
                    'bottom': 0,
                    'zIndex': 1,
                    '.MuiButtonBase-root': {
                        'transition': theme.transitions.create(['opacity'], {
                            duration: 100,
                        }),
                        'pointerEvents': 'auto',
                        'opacity': play ? 0 : undefined,
                        'bgcolor': 'primary.contrastText',
                        '&:hover': {
                            bgcolor: 'primary.contrastText',
                        },
                    },
                },
                '&:hover': {
                    '.MuiButtonBase-root': {
                        opacity: 1,
                    },
                },
            })}
        >
            {!controls && !autoPlay && !noInteraction && (
                <div className={playerActionsClass}>
                    <IconButton
                        onClick={onPlay}
                        onMouseDown={stopPropagationIfNoCtrl}
                        color={'primary'}
                    >
                        <PlayComponent
                            fontSize={getSizeCase(
                                Math.min(
                                    maxDimensions.width,
                                    maxDimensions.height
                                ),
                                {
                                    0: 'small',
                                    100: 'medium',
                                    250: 'large',
                                }
                            )}
                        />
                    </IconButton>
                </div>
            )}
            <ReactPlayer
                url={file.url}
                {...videoDimensions}
                playing={play || (!isAudio && autoPlay)}
                loop={true}
                onReady={player => {
                    onLoad && onLoad();
                    const internalPlayer = player.getInternalPlayer();
                    setRatio(
                        internalPlayer.videoHeight / internalPlayer.videoWidth
                    );
                    setDuration(player.getDuration());
                }}
                onPlay={() => setPlay(true)}
                onPause={() => setPlay(false)}
                onProgress={({played, loaded}) => {
                    setProgress({
                        played,
                        loaded,
                    });
                }}
                progressInterval={duration ? (duration < 60 ? 100 : 1000) : 5}
                muted={autoPlay}
                controls={hasControls}
            />
            {!hasControls && progress && (
                <LinearProgress
                    variant={progress ? 'buffer' : 'indeterminate'}
                    style={{
                        position: 'absolute',
                        bottom: 0,
                        left: 0,
                        right: 0,
                        zIndex: 1,
                    }}
                    value={progress ? progress.played * 100 : undefined}
                    valueBuffer={progress ? progress.loaded * 100 : undefined}
                />
            )}
        </Box>
    );
}
