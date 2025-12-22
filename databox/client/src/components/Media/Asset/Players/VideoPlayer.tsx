import {MouseEvent, useContext, useState} from 'react';
import {createStrictDimensions, PlayerProps} from './index';
import ReactPlayer from 'react-player/lazy';
import {IconButton, LinearProgress, SxProps} from '@mui/material';
import {DisplayContext} from '../../DisplayContext';
import PlayCircleIcon from '@mui/icons-material/PlayCircle';
import PauseIcon from '@mui/icons-material/Pause';
import {
    FileTypeEnum,
    getFileTypeFromMIMEType,
    getRatioDimensions,
    getSizeCase,
} from '@alchemy/core';
import {Theme} from '@mui/material/styles';
import assetClasses from '../../../AssetList/classes.ts';
import classNames from 'classnames';

type Progress = {
    played: number;
    loaded: number;
};

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
    onLoad,
    autoPlayable,
    noInteraction,
    controls,
    dimensions: forcedDimensions,
}: Props) {
    const [progress, setProgress] = useState<Progress>();
    const [duration, setDuration] = useState<number>();
    const displayContext = useContext(DisplayContext);
    const [play, setPlay] = useState(false);
    const [ratio, setRatio] = useState<number>();
    const type = getFileTypeFromMIMEType(file.type);
    const isAudio = type === FileTypeEnum.Audio;
    const d = displayContext?.state;
    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: d?.thumbSize ?? 200}
    );
    const videoDimensions = getRatioDimensions(dimensions, ratio);
    const autoPlay = autoPlayable && d?.playVideos;

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
        <div
            className={classNames({
                [assetClasses.videoPlayer]: true,
                [assetClasses.videoPlayerIsAudio]: isAudio,
                [assetClasses.videoPlayerPlaying]: play,
            })}
            style={{
                pointerEvents: hasControls ? 'auto' : undefined,
            }}
        >
            {!controls && !autoPlay && !noInteraction && (
                <div className={assetClasses.videoPlayerActions}>
                    <IconButton
                        onClick={onPlay}
                        onMouseDown={stopPropagationIfNoCtrl}
                        color={'primary'}
                    >
                        <PlayComponent
                            fontSize={getSizeCase(
                                Math.min(dimensions.width, dimensions.height),
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
        </div>
    );
}

export function videoPlayerSx(thumbSize: number, theme: Theme): SxProps {
    return {
        [`.${assetClasses.videoPlayer}`]: {
            position: 'relative',
            backgroundColor: theme.palette.common.black,
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            minWidth: thumbSize,
            minHeight: thumbSize,
            [`&.${assetClasses.videoPlayerIsAudio}`]: {
                backgroundColor: theme.palette.background.default,
            },
            [`.${assetClasses.videoPlayerActions}`]: {
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
                    'bgcolor': 'primary.contrastText',
                    '&:hover': {
                        bgcolor: 'primary.contrastText',
                    },
                },
            },

            [`&.${assetClasses.videoPlayerPlaying}`]: {
                [`.${assetClasses.videoPlayerActions}`]: {
                    opacity: 0,
                },
            },
            [`&.${assetClasses.videoPlayerPlaying}:hover`]: {
                [`.${assetClasses.videoPlayerActions}`]: {
                    opacity: 1,
                },
            },
        },
    };
}
