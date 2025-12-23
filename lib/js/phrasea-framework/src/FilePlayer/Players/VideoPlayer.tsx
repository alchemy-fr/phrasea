import {MouseEvent, SyntheticEvent, useRef, useState} from 'react';
import ReactPlayer from 'react-player';
import {IconButton, LinearProgress} from '@mui/material';
import PlayCircleIcon from '@mui/icons-material/PlayCircle';
import PauseIcon from '@mui/icons-material/Pause';
import {
    FileTypeEnum,
    getFileTypeFromMIMEType,
    getRatioDimensions,
} from '@alchemy/core';
import classNames from 'classnames';
import {FilePlayerClasses, FilePlayerProps} from '../types';

type Progress = {
    played: number;
    loaded: number;
};

function stopPropagationIfNoCtrl(e: MouseEvent) {
    if (!e.ctrlKey) {
        e.stopPropagation();
    }
}

type Props = {} & FilePlayerProps;

export default function VideoPlayer({
    file,
    onLoad,
    autoPlayable,
    noInteraction,
    controls,
    dimensions,
}: Props) {
    const playerRef = useRef<HTMLVideoElement | null>(null);
    const [progress, setProgress] = useState<Progress>();
    const type = getFileTypeFromMIMEType(file.type);
    const isAudio = type === FileTypeEnum.Audio;
    const [play, setPlay] = useState(!isAudio && autoPlayable);

    const onPlay = (e: MouseEvent) => {
        if (e.ctrlKey) {
            return;
        }
        e.stopPropagation();
        setPlay(p => !p);
    };

    const PlayComponent = play ? PauseIcon : PlayCircleIcon;

    const hasControls = !noInteraction && controls;

    const onUpdate = (e: SyntheticEvent<HTMLVideoElement>) => {
        const player = e.currentTarget;

        setProgress({
            played: player.currentTime / player.duration,
            loaded:
                player.buffered?.end(player.buffered?.length - 1) /
                player.duration,
        });
    }

    return (
        <div
            className={classNames({
                [FilePlayerClasses.PlayerControls]: true, // TODO move to controls
                [FilePlayerClasses.VideoPlayer]: true,
                [FilePlayerClasses.IsAudio]: isAudio,
                [FilePlayerClasses.Playing]: play,
            })}
            style={{
                pointerEvents: hasControls ? 'auto' : undefined,
            }}
        >
            {!controls && !autoPlayable && !noInteraction && (
                <div className={FilePlayerClasses.VideoPlayControl}>
                    <IconButton
                        onClick={onPlay}
                        onMouseDown={stopPropagationIfNoCtrl}
                        color={'primary'}
                    >
                        <PlayComponent />
                    </IconButton>
                </div>
            )}
            <ReactPlayer
                ref={playerRef}
                src={file.url}
                style={{
                    width: '100%',
                    height: '100%',
                    maxWidth: dimensions.width,
                    maxHeight: dimensions.height,
                }}
                playing={play}
                loop={true}
                onReady={onLoad}
                onPlay={() => setPlay(true)}
                onPause={() => setPlay(false)}
                onProgress={onUpdate}
                onTimeUpdate={onUpdate}
                onProgressCapture={() => {}}
                muted={autoPlayable}
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
                    sx={{
                        '.MuiLinearProgress-bar': {
                            transition: 'transform .2s linear',
                        },
                    }}
                    value={progress ? progress.played * 100 : undefined}
                    valueBuffer={progress ? progress.loaded * 100 : undefined}
                />
            )}
        </div>
    );
}
