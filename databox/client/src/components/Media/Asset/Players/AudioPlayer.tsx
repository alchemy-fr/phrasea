import {useCallback, useContext, useMemo, useRef} from 'react';
import {PlayerProps} from './index.ts';
import {useWavesurfer} from '@wavesurfer/react';
import Timeline from 'wavesurfer.js/dist/plugins/timeline.esm.js';
import {DisplayContext} from '../../DisplayContext.tsx';
import IconButton from '@mui/material/IconButton';
import PauseIcon from '@mui/icons-material/Pause';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import {Box, lighten, useTheme} from '@mui/material';
import {createStrictDimensions} from '@alchemy/core';
import useVisibility, {
    IsVisibleCallback,
} from '@alchemy/react-hooks/src/useVisibility.ts';
import classNames from 'classnames';
import assetClasses from '../../../AssetList/classes.ts';

type Props = {
    autoPlayable?: boolean;
    autoPlay?: boolean;
    controls?: boolean | undefined;
} & PlayerProps;

export default function AudioPlayer({
    autoPlay: initAutoPlay,
    autoPlayable,
    file,
    controls,
    dimensions: forcedDimensions,
}: Props) {
    const displayContext = useContext(DisplayContext);
    const d = displayContext?.state;
    const containerRef = useRef<HTMLDivElement | null>(null);
    const autoPlay = initAutoPlay ?? Boolean(autoPlayable && d?.playVideos);

    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: d?.thumbSize ?? 200}
    );
    const theme = useTheme();

    const {wavesurfer, isPlaying, currentTime, isReady} = useWavesurfer({
        container: containerRef,
        height: Math.min(dimensions.height, 256) * 0.4,
        width: dimensions.width,
        waveColor: lighten(theme.palette.primary.main, 0.5),
        progressColor: theme.palette.primary.main,
        url: file.url,
        interact: controls,
        plugins: useMemo(
            () => (controls ? [Timeline.create()] : []),
            [controls]
        ),
    });

    const onPlayPause = useCallback(() => {
        wavesurfer && wavesurfer.playPause();
    }, [wavesurfer]);

    const visibilityListener = useCallback<IsVisibleCallback>(
        isVisible => {
            if (!wavesurfer) {
                return false;
            }
            if (isVisible) {
                wavesurfer.play();
            } else {
                wavesurfer.pause();
            }
        },
        [wavesurfer]
    );

    useVisibility<HTMLDivElement>({
        shouldTrack: autoPlay,
        callback: visibilityListener,
        containerRef,
    });

    return (
        <div
            className={classNames({
                [assetClasses.audioPlayer]: true,
                [assetClasses.audioPlayerPlaying]: isPlaying,
            })}
        >
            <div ref={containerRef} />
            {wavesurfer && controls && (
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'row',
                        gap: 1,
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        mt: 1,
                        px: 2,
                    }}
                >
                    <IconButton onClick={onPlayPause} disabled={!isReady}>
                        {isPlaying ? (
                            <PauseIcon fontSize={'large'} />
                        ) : (
                            <PlayArrowIcon fontSize={'large'} />
                        )}
                    </IconButton>
                    <div
                        style={{
                            fontSize: '"Courier New", Courier, monospace',
                        }}
                    >
                        {formatDuration(currentTime)} /{' '}
                        {formatDuration(wavesurfer.getDuration())}
                    </div>
                </Box>
            )}
        </div>
    );
}

function formatDuration(duration: number): string {
    const hours = Math.floor(duration / 3600);
    const minutes = Math.floor((duration % 3600) / 60);
    const seconds = Math.floor(duration % 60);
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}
