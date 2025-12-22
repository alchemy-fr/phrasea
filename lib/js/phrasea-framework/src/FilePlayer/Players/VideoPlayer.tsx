import {MouseEvent, useState} from 'react';
import ReactPlayer from 'react-player/lazy';
import {IconButton, LinearProgress} from '@mui/material';
import PlayCircleIcon from '@mui/icons-material/PlayCircle';
import PauseIcon from '@mui/icons-material/Pause';
import {
    FileTypeEnum,
    getFileTypeFromMIMEType,
    getSizeCase,
} from '@alchemy/core';
import classNames from 'classnames';
import {FilePlayerProps} from '../types';

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
}: Props) {
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

    const hasControls = !noInteraction && controls;

    return (
        <div
            style={{
                pointerEvents: hasControls ? 'auto' : undefined,
            }}
        >
            {!controls && !autoPlayable && !noInteraction && (
                <div>
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
                url={file.url}
                playing={play || (!isAudio && autoPlayable)}
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
