'use client';

import {
    type CSSProperties,
    type RefObject,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
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
import type {ClipCrop, ClipTimeRange, MediaClip} from '@/types/api';
import {cn} from '@alchemy/next-ui/utils/cn';
import {scanForMedia} from '@alchemy/next-core/analytics/matomo';
import type {ContentTracking} from '@/lib/analytics/useContentTracking';
import {ClipFrame} from '@/features/clip/ClipFrame';
import {useClipPlayback} from '@/features/clip/useClipPlayback';
import {CropOverlay} from '@/features/assets/crop/CropOverlay';
import {useAnnotationLayer} from '@/features/annotations/useAnnotationLayer';
import {
    defaultFrameDuration,
    type AnnotationTimeline,
} from '@/features/annotations/common';
import {getAnnotationLabel} from '@/features/annotations/annotationNames';
import {
    AnnotationType,
    type AssetAnnotation,
} from '@/features/annotations/types';
import type {SubtitleTrack} from './subtitles';
import {containBox, type Box} from './videoBox';

export type VideoJsPlayerProps = {
    src: string;
    type: string;
    autoPlay?: boolean;
    className?: string;
    tracking?: ContentTracking;
    /** The playback is bounded to these ranges, shown on the time slider */
    timeRanges?: ClipTimeRange[];
    /** Only this part of the frame is shown */
    crop?: ClipCrop;
    /**
     * The region of interest of the whole video shown: its ranges on the
     * time slider and its crop outlined, without bounding nor cropping
     */
    roiOverlay?: MediaClip;
    /** Offered in the subtitles menu, none shown by default */
    subtitles?: SubtitleTrack[];
    /**
     * Shows the annotations of the asset view over the frame they are drawn
     * on, and their marks on the time slider
     */
    annotate?: boolean;
};

/**
 * Video player built on Video.js, its controls styled for the application.
 *
 * In an asset view, the annotation layer (see `features/annotations`) is
 * over the frame displayed: a shape is drawn on the frame the video is paused
 * on (a drawing tool pauses it) and shown on that frame only, a cue marks the
 * current time. Every timed annotation has its mark on the time slider;
 * selecting one, from there or from elsewhere (a message, a value), pauses
 * the video on its frame.
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

type MediaInstant = {
    paused: boolean;
    /** The current time, followed while paused only */
    time: number;
    duration: number;
};

/** Paused or playing, the time shown while paused, the duration */
function useMediaInstant(
    ref: RefObject<HTMLVideoElement | null>
): MediaInstant {
    const [instant, setInstant] = useState<MediaInstant>({
        paused: true,
        time: 0,
        duration: 0,
    });

    useEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        const read = () =>
            setInstant(prev => {
                const paused = el.paused;
                const time = paused ? el.currentTime : prev.time;
                const duration = Number.isFinite(el.duration) ? el.duration : 0;

                return prev.paused === paused &&
                    prev.time === time &&
                    prev.duration === duration
                    ? prev
                    : {paused, time, duration};
            });
        const events = [
            'play',
            'pause',
            'seeked',
            'timeupdate',
            'loadedmetadata',
            'durationchange',
            'emptied',
        ];
        read();
        events.forEach(e => el.addEventListener(e, read));

        return () => events.forEach(e => el.removeEventListener(e, read));
    }, [ref]);

    return instant;
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

/** The box of the frame displayed, in the coordinates of the container */
function useVideoBox(
    videoRef: RefObject<HTMLVideoElement | null>,
    containerRef: RefObject<HTMLDivElement | null>,
    enabled: boolean
): Box | undefined {
    const [box, setBox] = useState<Box>();

    useEffect(() => {
        const video = videoRef.current;
        const container = containerRef.current;
        if (!enabled || !video || !container) {
            setBox(undefined);

            return;
        }
        const measure = () => {
            const c = container.getBoundingClientRect();
            const v = video.getBoundingClientRect();
            const next = containBox(
                {
                    left: v.left - c.left,
                    top: v.top - c.top,
                    width: v.width,
                    height: v.height,
                },
                video.videoWidth,
                video.videoHeight
            );
            setBox(prev =>
                prev &&
                prev.left === next.left &&
                prev.top === next.top &&
                prev.width === next.width &&
                prev.height === next.height
                    ? prev
                    : next
            );
        };
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(container);
        observer.observe(video);
        video.addEventListener('loadedmetadata', measure);
        video.addEventListener('resize', measure);

        return () => {
            observer.disconnect();
            video.removeEventListener('loadedmetadata', measure);
            video.removeEventListener('resize', measure);
        };
    }, [videoRef, containerRef, enabled]);

    return box;
}

function isTimed(
    annotation: AssetAnnotation
): annotation is AssetAnnotation & {t: number} {
    return typeof annotation.t === 'number' && Number.isFinite(annotation.t);
}

function VideoJsPlayerBody({
    src,
    type,
    autoPlay,
    className,
    tracking,
    timeRanges,
    crop,
    roiOverlay,
    subtitles,
    annotate = false,
}: VideoJsPlayerProps) {
    const {t} = useTranslation();
    const ref = useRef<HTMLVideoElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const trackingId = tracking?.id;
    const [aspect, setAspect] = useState<number>();
    const instant = useMediaInstant(ref);
    const tracks = useSubtitleUrls(subtitles);
    useClipPlayback(ref, timeRanges);
    const overlayCrop = crop ? undefined : roiOverlay?.crop;

    const timeline = useMemo<AnnotationTimeline>(
        () => ({
            time: instant.paused ? instant.time : undefined,
            tolerance: defaultFrameDuration,
            now: () => ref.current?.currentTime ?? 0,
        }),
        [instant.paused, instant.time]
    );
    // A cropped video is not the frame the shapes are measured against
    const annotations = useAnnotationLayer({
        zoom: 1,
        timeline,
        enabled: annotate && !crop,
    });
    const {editor, target, isFallback} = annotations;
    const box = useVideoBox(
        ref,
        containerRef,
        !!annotations.layer || !!overlayCrop
    );

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

    /** Pauses on the frame of the time (before the metadata is loaded too) */
    const seekTo = useCallback((time: number) => {
        const el = ref.current;
        if (!el) {
            return;
        }
        el.pause();
        if (Math.abs(el.currentTime - time) > 1e-3) {
            el.currentTime = time;
        }
    }, []);

    // A shape is drawn on the frame shown: a drawing tool pauses the video,
    // playing leaves the tool
    const tool = editor.tool;
    useEffect(() => {
        if (tool) {
            ref.current?.pause();
        }
    }, [tool]);
    const editorRef = useRef(editor);
    editorRef.current = editor;
    useEffect(() => {
        if (!instant.paused && editorRef.current.tool) {
            editorRef.current.setTool(undefined);
        }
    }, [instant.paused]);

    // The annotation selected (here or from elsewhere: a message, a value)
    // is shown on its frame; a message shown without one, its first one
    const selected = editor.selected;
    useEffect(() => {
        if (selected && isTimed(selected)) {
            seekTo(selected.t);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected?.id, seekTo]);
    const targetKey = target?.key;
    useEffect(() => {
        if (target && !isFallback && !target.selectedId) {
            const first = editor.annotations.find(isTimed);
            if (first) {
                seekTo(first.t);
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [targetKey]);

    const markers = annotations.target
        ? editor.annotations.filter(isTimed)
        : [];

    const video = (style?: CSSProperties, mediaClassName?: string) => (
        <Video
            ref={ref}
            src={src}
            className={
                mediaClassName ??
                cn('block max-h-full max-w-full object-contain', className)
            }
            style={style}
            autoPlay={autoPlay}
            muted={autoPlay}
            playsInline
            preload="metadata"
            data-type={type}
            data-matomo-resource={trackingId}
            data-matomo-title={trackingId ? tracking?.title : undefined}
            onLoadedMetadata={e => {
                const {videoWidth, videoHeight} = e.currentTarget;
                if (videoWidth > 0 && videoHeight > 0) {
                    setAspect(videoWidth / videoHeight);
                }
            }}
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
    );

    return (
        <Container
            ref={containerRef}
            data-testid="video-player"
            className={cn(
                'relative flex size-full items-center justify-center overflow-hidden',
                className
            )}
        >
            {crop ? (
                <ClipFrame crop={crop} aspect={aspect}>
                    {media => video(media.style, media.className)}
                </ClipFrame>
            ) : (
                video()
            )}
            {overlayCrop && box ? (
                <div
                    data-testid="roi-overlay"
                    className="pointer-events-none absolute"
                    style={{
                        left: box.left,
                        top: box.top,
                        width: box.width,
                        height: box.height,
                    }}
                >
                    <CropOverlay crop={overlayCrop} dimmed={false} />
                </div>
            ) : null}
            {annotations.layer && box ? (
                // The frame displayed; the controls stay above it
                <div
                    data-testid="annotation-overlay"
                    data-interactive
                    className="pointer-events-none absolute isolate"
                    style={{
                        left: box.left,
                        top: box.top,
                        width: box.width,
                        height: box.height,
                    }}
                >
                    {annotations.layer}
                </div>
            ) : null}
            {annotations.toolbar ? (
                <div
                    data-interactive
                    // Nothing follows the tools: no separator after them
                    className="absolute top-3 left-1/2 z-30 flex -translate-x-1/2 items-center gap-1 rounded-full border bg-background/90 p-1 text-foreground shadow-md [&>[data-annotation-toolbar]>span:last-child]:hidden [&>span:last-child]:hidden"
                >
                    {annotations.toolbar}
                </div>
            ) : null}
            <VideoControls
                markers={markers}
                ranges={timeRanges ?? roiOverlay?.timeRanges}
                duration={instant.duration}
                selectedId={editor.selectedId}
                labelOf={a => getAnnotationLabel(t, a)}
                onMarker={a => {
                    seekTo(a.t);
                    editor.select(a.id);
                }}
            />
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

function VideoControls({
    markers,
    ranges,
    duration,
    selectedId,
    labelOf,
    onMarker,
}: {
    markers: (AssetAnnotation & {t: number})[];
    /** Time ranges of the clip, as bands on the time slider */
    ranges?: ClipTimeRange[];
    duration: number;
    selectedId: string | undefined;
    labelOf: (annotation: AssetAnnotation) => string;
    onMarker: (annotation: AssetAnnotation & {t: number}) => void;
}) {
    const {t} = useTranslation();

    return (
        <Controls.Root>
            <Controls.Content className="group/controls pointer-events-none absolute inset-x-0 bottom-0 z-20 flex flex-col gap-1 bg-linear-to-t text-white from-black/70 to-transparent px-3 pt-10 pb-2 transition-opacity duration-200 motion-reduce:transition-none [&:not([data-visible]):not(:has(:focus-visible))]:opacity-0">
                <TimeSlider.Root className="group/slider pointer-events-none relative flex h-5 cursor-pointer items-center group-data-visible/controls:pointer-events-auto">
                    <TimeSlider.Track className="relative h-1 w-full overflow-hidden rounded-full bg-white/30 transition-[height] duration-150 group-data-interactive/slider:h-1.5">
                        <TimeSlider.Buffer className="absolute inset-y-0 left-0 w-(--media-slider-buffer) bg-white/40" />
                        <TimeSlider.Fill className="absolute inset-y-0 left-0 w-(--media-slider-fill) bg-primary" />
                    </TimeSlider.Track>
                    {duration > 0 && ranges?.length ? (
                        <div
                            data-testid="clip-ranges"
                            className="pointer-events-none absolute inset-x-0 top-1/2 h-1 -translate-y-1/2"
                        >
                            {ranges.map(r => {
                                const left = Math.min(
                                    100,
                                    Math.max(0, (r.s / duration) * 100)
                                );
                                const right = Math.min(
                                    100,
                                    Math.max(left, (r.e / duration) * 100)
                                );

                                return (
                                    <div
                                        key={`${r.s}-${r.e}`}
                                        data-clip-range
                                        className="absolute inset-y-0 rounded-full bg-amber-300/80"
                                        style={{
                                            left: `${left}%`,
                                            width: `${right - left}%`,
                                        }}
                                    />
                                );
                            })}
                        </div>
                    ) : null}
                    <TimeSlider.Thumb className="absolute left-(--media-slider-fill) size-3 -translate-x-1/2 scale-0 rounded-full bg-white shadow outline-none transition-transform duration-150 group-data-interactive/slider:scale-100 focus-visible:ring-2 focus-visible:ring-white/70" />
                    {duration > 0
                        ? markers.map(annotation => {
                              const label = labelOf(annotation);
                              const left = `${Math.min(100, Math.max(0, (annotation.t / duration) * 100))}%`;
                              const cue =
                                  annotation.type === AnnotationType.Cue;

                              return (
                                  <button
                                      key={annotation.id}
                                      type="button"
                                      data-annotation-marker={annotation.id}
                                      data-selected={
                                          annotation.id === selectedId
                                              ? 'true'
                                              : undefined
                                      }
                                      title={label}
                                      aria-label={label}
                                      className={cn(
                                          'absolute top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 cursor-pointer border border-black/40 bg-amber-400 shadow outline-none hover:scale-125 focus-visible:ring-2 focus-visible:ring-white/70 data-selected:bg-white',
                                          cue
                                              ? 'h-3.5 w-1.5 rounded-sm'
                                              : 'size-2.5 rounded-full'
                                      )}
                                      style={{left}}
                                      onPointerDown={e => e.stopPropagation()}
                                      onClick={e => {
                                          e.stopPropagation();
                                          onMarker(annotation);
                                      }}
                                  />
                              );
                          })
                        : null}
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
