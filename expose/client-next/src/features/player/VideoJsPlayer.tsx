'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    CaptionsRadioGroup,
    Container,
    Controls,
    FullscreenButton,
    Gesture,
    Hotkey,
    Menu,
    MuteButton,
    PlayButton,
    PlaybackRateButton,
    Time,
    TimeSlider,
    VolumeSlider,
} from '@videojs/react';
import {I18nProvider} from '@videojs/react/i18n';
import {usePlayer, Video, VideoPlayer} from '@videojs/react/video';
import {
    CaptionsIcon,
    CheckIcon,
    MaximizeIcon,
    MinimizeIcon,
    PauseIcon,
    PlayIcon,
    RotateCcwIcon,
    Volume1Icon,
    Volume2Icon,
    VolumeXIcon,
} from 'lucide-react';
import {cn} from '@alchemy/next-ui/utils/cn';
import {scanForMedia} from '@alchemy/next-core/analytics/matomo';
import type {ContentTracking} from '@/lib/analytics/useContentTracking';
import type {SubtitleTrack} from './subtitles';

export type VideoJsPlayerProps = {
    src: string;
    type: string;
    autoPlay?: boolean;
    className?: string;
    /** Image shown until the video plays */
    poster?: string;
    tracking?: ContentTracking;
    /** Offered in the subtitles menu, none shown by default */
    subtitles?: SubtitleTrack[];
};

/**
 * Video player built on Video.js, its controls styled for the application.
 */
export function VideoJsPlayer(props: VideoJsPlayerProps) {
    const {i18n} = useTranslation();

    return (
        <I18nProvider locale={i18n.language}>
            <VideoPlayer>
                <VideoJsPlayerBody {...props} />
            </VideoPlayer>
        </I18nProvider>
    );
}

/** Object URLs of the subtitle tracks, revoked when they change */
function useSubtitleUrls(
    subtitles: SubtitleTrack[] | undefined
): (SubtitleTrack & {url: string})[] {
    const [tracks, setTracks] = useState<(SubtitleTrack & {url: string})[]>([]);

    useEffect(() => {
        if (!subtitles?.length) {
            setTracks([]);

            return;
        }
        const created = subtitles.map(s => ({
            ...s,
            url: URL.createObjectURL(new Blob([s.src], {type: 'text/vtt'})),
        }));
        setTracks(created);

        return () => created.forEach(c => URL.revokeObjectURL(c.url));
    }, [subtitles]);

    return tracks;
}

function VideoJsPlayerBody({
    src,
    type,
    autoPlay,
    className,
    poster,
    tracking,
    subtitles,
}: VideoJsPlayerProps) {
    const ref = useRef<HTMLVideoElement>(null);
    const trackingId = tracking?.id;
    const tracks = useSubtitleUrls(subtitles);

    // MediaAnalytics reads the `data-matomo-*` attributes when it scans the
    // container, so they are rendered first
    useEffect(() => {
        const container = ref.current?.parentElement;
        if (trackingId && container) {
            scanForMedia(container);
        }
    }, [trackingId]);

    // Pause when scrolled out of view
    useEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (!e.isIntersecting && !el.paused) {
                    el.pause();
                }
            });
        });
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return (
        <Container
            data-testid="video-player"
            className={cn(
                'relative flex size-full items-center justify-center overflow-hidden',
                className
            )}
        >
            <Video
                ref={ref}
                src={src}
                className="block max-h-full max-w-full object-contain"
                poster={poster}
                autoPlay={autoPlay}
                muted={autoPlay}
                playsInline
                preload="metadata"
                data-type={type}
                data-matomo-resource={trackingId}
                data-matomo-title={trackingId ? tracking?.title : undefined}
            >
                {tracks.map(track => (
                    <track
                        key={track.url}
                        kind="subtitles"
                        src={track.url}
                        srcLang={track.locale}
                        label={track.label}
                    />
                ))}
            </Video>
            <VideoControls />
            <Hotkey keys="Space" action="togglePaused" />
            <Hotkey keys="k" action="togglePaused" />
            <Hotkey keys="m" action="toggleMuted" />
            <Hotkey keys="f" action="toggleFullscreen" />
            <Hotkey keys="c" action="toggleSubtitles" />
            <Gesture
                type="tap"
                action="togglePaused"
                pointer="mouse"
                region="center"
            />
            <Gesture type="tap" action="toggleControls" pointer="touch" />
            <Gesture
                type="doubletap"
                action="toggleFullscreen"
                region="center"
            />
        </Container>
    );
}

const controlButton =
    'inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-white outline-none hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/70 [&_svg]:size-4.5';

function VideoControls() {
    const {t} = useTranslation();

    return (
        <Controls.Root>
            <Controls.Content className="group/controls pointer-events-none absolute inset-x-0 bottom-0 z-20 flex flex-col gap-1 bg-linear-to-t text-white from-black/70 to-transparent px-3 pt-10 pb-2 transition-opacity duration-200 motion-reduce:transition-none [&:not([data-visible]):not(:has(:focus-visible))]:opacity-0">
                <TimeSlider.Root className="group/slider pointer-events-none relative flex h-5 cursor-pointer items-center group-data-visible/controls:pointer-events-auto">
                    <TimeSlider.Track className="relative h-1 w-full overflow-hidden rounded-full bg-white/30 transition-[height] duration-150 group-data-interactive/slider:h-1.5">
                        <TimeSlider.Buffer className="absolute inset-y-0 left-0 w-(--media-slider-buffer) bg-white/40" />
                        <TimeSlider.Fill className="absolute inset-y-0 left-0 w-(--media-slider-fill) bg-primary" />
                    </TimeSlider.Track>
                    <TimeSlider.Thumb className="absolute left-(--media-slider-fill) size-3 -translate-x-1/2 scale-0 rounded-full bg-white shadow outline-none transition-transform duration-150 group-data-interactive/slider:scale-100 focus-visible:ring-2 focus-visible:ring-white/70" />
                    <TimeSlider.Preview className="pointer-events-none absolute bottom-full left-(--media-slider-pointer) mb-2 -translate-x-1/2 rounded bg-black/80 px-1.5 py-0.5 text-xs whitespace-nowrap tabular-nums opacity-0 transition-opacity group-data-pointing/slider:opacity-100">
                        <TimeSlider.Value type="pointer" />
                    </TimeSlider.Preview>
                </TimeSlider.Root>
                <div className="pointer-events-none flex items-center gap-1 text-sm group-data-visible/controls:pointer-events-auto">
                    {/* The icons follow the state the buttons tell in data attributes */}
                    <PlayButton className={controlButton}>
                        <PlayIcon className="hidden [[data-paused]:not([data-ended])>&]:block" />
                        <PauseIcon className="[[data-paused]>&]:hidden" />
                        <RotateCcwIcon className="hidden [[data-ended]>&]:block" />
                    </PlayButton>
                    <div className="group/volume flex items-center">
                        <MuteButton className={controlButton}>
                            <VolumeXIcon className="hidden [[data-volume-level=off]>&]:block" />
                            <Volume1Icon className="hidden [[data-volume-level=low]>&]:block [[data-volume-level=medium]>&]:block" />
                            <Volume2Icon className="hidden [[data-volume-level=high]>&]:block" />
                        </MuteButton>
                        <VolumeSlider.Root className="group/vslider relative flex h-5 w-0 cursor-pointer items-center overflow-hidden opacity-0 transition-[width,opacity] duration-150 group-focus-within/volume:w-20 group-focus-within/volume:opacity-100 group-hover/volume:w-20 group-hover/volume:opacity-100">
                            <VolumeSlider.Track className="relative mx-1.5 h-1 w-full overflow-hidden rounded-full bg-white/30">
                                <VolumeSlider.Fill className="absolute inset-y-0 left-0 w-(--media-slider-fill) bg-white" />
                            </VolumeSlider.Track>
                            <VolumeSlider.Thumb className="absolute left-(--media-slider-fill) size-2.5 -translate-x-1/2 rounded-full bg-white outline-none focus-visible:ring-2 focus-visible:ring-white/70" />
                        </VolumeSlider.Root>
                    </div>
                    <Time.Group className="px-1.5 text-xs whitespace-nowrap tabular-nums">
                        <Time.Value type="current" />
                        <Time.Separator className="px-0.5 opacity-70" />
                        <Time.Value type="duration" className="opacity-70" />
                    </Time.Group>
                    <span className="flex-1" />
                    <Menu.Root side="top" align="end">
                        <CaptionsRadioGroup.Root>
                            <Menu.Trigger
                                className={cn(
                                    controlButton,
                                    'data-active:text-primary [&[hidden]]:hidden'
                                )}
                                title={t('player.subtitles', 'Subtitles')}
                            >
                                <CaptionsIcon />
                            </Menu.Trigger>
                            <Menu.Popup
                                data-testid="subtitles-menu"
                                className="m-0 h-(--media-menu-height) max-h-(--media-menu-available-height) w-(--media-menu-width) min-w-44 overflow-hidden rounded-md border-0 bg-black/85 p-0 text-sm text-white shadow-lg backdrop-blur [--media-popover-side-offset:0.5rem]"
                            >
                                <Menu.Content className="absolute inset-x-0 top-0 flex flex-col overflow-auto p-1 outline-none">
                                    <CaptionsRadioGroup.Options
                                        className="flex flex-col"
                                        renderItem={(props, item) => (
                                            <Menu.RadioItem
                                                {...props}
                                                className="flex cursor-pointer items-center justify-between gap-3 rounded px-2 py-1.5 outline-none select-none data-highlighted:bg-white/15"
                                            >
                                                <span className="truncate">
                                                    {item.label}
                                                </span>
                                                <Menu.ItemIndicator
                                                    checked={item.checked}
                                                >
                                                    <CheckIcon className="size-4" />
                                                </Menu.ItemIndicator>
                                            </Menu.RadioItem>
                                        )}
                                    />
                                </Menu.Content>
                            </Menu.Popup>
                        </CaptionsRadioGroup.Root>
                    </Menu.Root>
                    <PlaybackRateButton
                        className={cn(
                            controlButton,
                            'w-auto min-w-8 px-1 text-xs tabular-nums'
                        )}
                    >
                        <PlaybackRate />
                    </PlaybackRateButton>
                    <FullscreenButton className={controlButton}>
                        <MaximizeIcon className="[[data-fullscreen]>&]:hidden" />
                        <MinimizeIcon className="hidden [[data-fullscreen]>&]:block" />
                    </FullscreenButton>
                </div>
            </Controls.Content>
        </Controls.Root>
    );
}

function PlaybackRate() {
    const rate = usePlayer(s => s.playbackRate);

    return <>{rate ?? 1}×</>;
}
