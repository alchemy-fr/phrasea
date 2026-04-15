import {
    MouseEvent,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import {
    createStrictDimensions,
    getRatioDimensions,
    getSizeCase,
} from '@alchemy/core';
import {PlayerProps} from './index.ts';
import ReactPlayer from 'react-player/lazy';
import {IconButton, LinearProgress, SxProps, Theme} from '@mui/material';
import {DisplayContext} from '../../DisplayContext';
import PlayCircleIcon from '@mui/icons-material/PlayCircle';
import PauseIcon from '@mui/icons-material/Pause';
import assetClasses from '../../../AssetList/classes.ts';
import classNames from 'classnames';
import {useMatomo} from '@alchemy/phrasea-framework';
import type {IsVisibleCallback} from '@alchemy/react-hooks/src/useVisibility.ts';
import useVisibility from '@alchemy/react-hooks/src/useVisibility.ts';

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
    autoPlayable?: boolean;
    autoPlay?: boolean;
    controls?: boolean | undefined;
} & PlayerProps;

export default function VideoPlayer({
    file,
    title,
    trackingId,
    onLoad,
    autoPlayable,
    autoPlay: initAutoPlay,
    noInteraction,
    controls,
    dimensions: forcedDimensions,
}: Props) {
    const [progress, setProgress] = useState<Progress>();
    const [duration, setDuration] = useState<number>();
    const displayContext = useContext(DisplayContext);
    const d = displayContext?.state;
    const [play, setPlay] = useState(false);
    const [ratio, setRatio] = useState<number>();
    const dimensions = createStrictDimensions(
        forcedDimensions ?? {width: d?.thumbSize ?? 200}
    );
    const videoDimensions = getRatioDimensions(dimensions, ratio);
    const autoPlay = initAutoPlay ?? Boolean(autoPlayable && d?.playVideos);
    const playerRef = useRef<ReactPlayer | null>(null);

    const {pushInstruction} = useMatomo();

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

    useEffect(() => {
        if (playerRef.current) {
            const videoElement = playerRef.current.getInternalPlayer();

            if (trackingId !== undefined && videoElement) {
                pushInstruction('MediaAnalytics::scanForMedia');

                videoElement.setAttribute('data-matomo-resource', trackingId);

                if (title) {
                    videoElement.setAttribute('data-matomo-title', title);
                }
            }
        }
    }, [playerRef]);

    const visibilityListener = useCallback<IsVisibleCallback>(
        isVisible => {
            const internalPlayer = playerRef.current?.getInternalPlayer();
            if (!internalPlayer) {
                return false;
            }
            if (isVisible) {
                internalPlayer.play();
            } else {
                internalPlayer.pause();
            }
        },
        [playerRef.current]
    );

    const {elementRef} = useVisibility<HTMLDivElement>({
        shouldTrack: autoPlay,
        callback: visibilityListener,
    });

    return (
        <div
            ref={elementRef}
            className={classNames({
                [assetClasses.videoPlayer]: true,
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
                ref={playerRef}
                url={file.url}
                {...videoDimensions}
                playing={play}
                loop={true}
                onReady={player => {
                    onLoad?.();
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
            [`&.${assetClasses.audioPlayer}`]: {
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
