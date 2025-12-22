import {MouseEvent, useRef, useState} from 'react';
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
    const [duration, setDuration] = useState<number>();
    const [play, setPlay] = useState(false);
    const [ratio, setRatio] = useState<number>();
    const type = getFileTypeFromMIMEType(file.type);
    const isAudio = type === FileTypeEnum.Audio;

    const onPlay = (e: MouseEvent) => {
        if (e.ctrlKey) {
            return;
        }
        e.stopPropagation();
        setPlay(p => !p);
    };

    const PlayComponent = play ? PauseIcon : PlayCircleIcon;

    const videoDimensions = getRatioDimensions(dimensions, ratio);
    const hasControls = !noInteraction && controls;

    return (
        <div
            className={classNames({
                [FilePlayerClasses.VideoPlayer]: true,
                [FilePlayerClasses.IsAudio]: isAudio,
                [FilePlayerClasses.Playing]: play,
            })}
            style={{
                pointerEvents: hasControls ? 'auto' : undefined,
            }}
        >
            {!controls && !autoPlayable && !noInteraction && (
                <div className={FilePlayerClasses.Controls}>
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
                playing={play || (!isAudio && autoPlayable)}
                loop={true}
                onReady={() => {
                    onLoad && onLoad();
                    const internalPlayer = playerRef.current;
                    if (internalPlayer) {
                        setRatio(
                            internalPlayer.videoHeight /
                                internalPlayer.videoWidth
                        );
                        setDuration(internalPlayer.duration);
                    }
                }}
                onPlay={() => setPlay(true)}
                onPause={() => setPlay(false)}
                onProgress={(e) => {
                    const {played, buffered} = e.currentTarget;

                    setProgress({
                        played: played.end(0),
                        loaded: buffered.end(0),
                    });
                }}
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
                    value={progress ? progress.played * 100 : undefined}
                    valueBuffer={progress ? progress.loaded * 100 : undefined}
                />
            )}
        </div>
    );
}
