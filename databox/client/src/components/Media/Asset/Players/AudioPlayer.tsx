import {useCallback, useContext, useMemo, useRef} from 'react';
import {PlayerProps} from './index.ts';
import {useWavesurfer} from '@wavesurfer/react';
import Timeline from 'wavesurfer.js/dist/plugins/timeline.esm.js';
import {DisplayContext} from '../../DisplayContext.tsx';
import IconButton from '@mui/material/IconButton';
import PauseIcon from '@mui/icons-material/Pause';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import {lighten, Stack, useTheme} from '@mui/material';

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
    dimensions,
}: Props) {
    const displayContext = useContext(DisplayContext);
    const d = displayContext?.state;
    const containerRef = useRef<HTMLDivElement | null>(null);
    const autoPlay = initAutoPlay ?? Boolean(autoPlayable && d?.playVideos);

    const height = Math.min(dimensions?.height ?? 128, 256);
    const theme = useTheme();

    const {wavesurfer, isPlaying, currentTime, isReady} = useWavesurfer({
        container: containerRef,
        height: height,
        width: dimensions?.width ?? 128,
        waveColor: lighten(theme.palette.primary.main, 0.5),
        progressColor: theme.palette.primary.main,
        url: file.url,
        plugins: useMemo(() => [Timeline.create()], []),
        autoplay: autoPlay,
    });

    const onPlayPause = useCallback(() => {
        wavesurfer && wavesurfer.playPause();
    }, [wavesurfer]);

    return (
        <>
            <div ref={containerRef} />
            {controls && (
                <Stack direction={'row'} gap={2}>
                    <IconButton
                        size={'large'}
                        onClick={onPlayPause}
                        disabled={!isReady}
                    >
                        {isPlaying ? (
                            <PauseIcon fontSize={'large'} />
                        ) : (
                            <PlayArrowIcon fontSize={'large'} />
                        )}
                    </IconButton>
                    <div>
                        {Math.round(currentTime)} / {wavesurfer?.getDuration()}
                    </div>
                </Stack>
            )}
        </>
    );
}
