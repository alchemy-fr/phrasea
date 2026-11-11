'use client';

import {type KeyboardEvent, useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {MaximizeIcon, ZoomInIcon, ZoomOutIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {ClipTimeRange} from './types';
import {formatClipRange} from './clip';
import {
    AUDIO_FRAME_PX,
    formatStepDuration,
    frameIndex,
    getTicks,
    isInView,
    snapToFrame,
    timeToPercent,
    VIDEO_FRAME_PX,
} from './timeline';
import {createVideoFrameCache} from './frameCanvas';
import {VideoFilmstrip} from './VideoFilmstrip';
import {AudioWaveform} from './AudioWaveform';
import {TimelineRuler} from './TimelineRuler';
import {TimelineOverview} from './TimelineOverview';
import {FrameRateSelect} from './FrameRateSelect';
import {useTimelineDrag} from './useTimelineDrag';
import {useTimelineView} from './useTimelineView';
import {useFramePreview} from './useFramePreview';
import {RangeHandle, TimelineHoverPreview} from './TimelineParts';
import type {FrameRateState} from './useFrameRate';

/** Zoom of a button or a key press */
const ZOOM_STEP = 2;

export type ClipTimelineProps = {
    /** A video has frames (its thumbnails are shown), a sound a waveform */
    kind: 'video' | 'audio';
    ranges: ClipTimeRange[];
    /** Index of the selected range, -1 for none */
    selected: number;
    /** Indexes of the ranges ending before they start */
    invalid?: number[];
    currentTime: number;
    duration: number;
    /** Every time is snapped to it, in seconds */
    frameDuration: number;
    /** Whether a range can be drawn (fewer than the limit) */
    canCreate: boolean;
    videoSrc?: string;
    /** Width / height of the video */
    aspect?: number;
    /** Waveform of the sound, in [-1, 1] */
    peaks?: number[];
    /** Frame rate of the video, chosen with a select */
    frameRate?: FrameRateState;
    onChange: (index: number, range: ClipTimeRange) => void;
    onSelect: (index: number) => void;
    onSeek: (time: number) => void;
    /** A range drawn, or, without one, a range starting at the playhead */
    onCreate: (range?: ClipTimeRange) => void;
};

/**
 * The timeline of the clip editor: the media drawn as in an editing software
 * (thumbnails of a video, waveform of a sound) under a ruler, the ranges on
 * it, and a bar of the whole duration below. It zooms down to the frame
 * (ctrl + wheel, buttons, keys), the precision of a graduation shown aside.
 *
 * With the pointer: a click moves the playhead, a drag on the background
 * draws a range, the selected range is resized by its handles and moved by
 * its body. With the keyboard (timeline focused): ←/→ one frame (shift: one
 * second), I/O start/end of the selected range at the playhead, +/− zoom, 0
 * the whole duration.
 */
export function ClipTimeline({
    kind,
    ranges,
    selected,
    invalid = [],
    currentTime,
    duration,
    frameDuration,
    canCreate,
    videoSrc,
    aspect,
    peaks,
    frameRate,
    onChange,
    onSelect,
    onSeek,
    onCreate,
}: ClipTimelineProps) {
    const {t} = useTranslation();
    const rootRef = useRef<HTMLDivElement>(null);
    const laneRef = useRef<HTMLDivElement>(null);
    const [hover, setHover] = useState<number>();
    const isVideo = kind === 'video';
    const {width, minSpan, view, zoom, fit, scrollTo} = useTimelineView({
        rootRef,
        laneRef,
        duration,
        frameDuration,
        framePx: isVideo ? VIDEO_FRAME_PX : AUDIO_FRAME_PX,
        currentTime,
    });

    const ticks = getTicks(view, width, frameDuration, isVideo);
    const snap = (time: number) =>
        Math.min(duration, Math.max(0, snapToFrame(time, frameDuration)));

    const cache = useMemo(
        () =>
            videoSrc && frameDuration > 0 ? createVideoFrameCache() : undefined,
        [videoSrc, frameDuration]
    );
    const {preview, request} = useFramePreview({
        src: isVideo ? videoSrc : undefined,
        cache,
        frameDuration,
        duration,
    });

    const {draft, dragging, handlers, rulerHandlers} = useTimelineDrag({
        laneRef,
        view,
        duration,
        frameDuration,
        ranges,
        selected,
        canCreate,
        onChange,
        onSelect,
        onSeek,
        onCreate,
        onHover: time => {
            setHover(time);
            if (isVideo) {
                request(time);
            }
        },
    });

    const range = selected >= 0 ? ranges[selected] : undefined;

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        if (e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        const step = e.shiftKey ? 1 : frameDuration;
        switch (e.key) {
            case 'ArrowLeft':
                onSeek(snap(currentTime - step));
                break;
            case 'ArrowRight':
                onSeek(snap(currentTime + step));
                break;
            case 'i':
            case 'I':
                if (range) {
                    onChange(selected, {...range, s: snap(currentTime)});
                } else if (canCreate) {
                    onCreate();
                }
                break;
            case 'o':
            case 'O':
                if (range) {
                    onChange(selected, {...range, e: snap(currentTime)});
                }
                break;
            case '+':
            case '=':
                zoom(ZOOM_STEP);
                break;
            case '-':
            case '_':
                zoom(1 / ZOOM_STEP);
                break;
            case '0':
                fit();
                break;
            default:
                return;
        }
        e.preventDefault();
    };

    const onHandleKeyDown = (
        e: KeyboardEvent<HTMLDivElement>,
        edge: 's' | 'e'
    ) => {
        if (!range || (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const delta =
            (e.key === 'ArrowLeft' ? -1 : 1) * (e.shiftKey ? 1 : frameDuration);
        if (edge === 's') {
            const s = Math.min(snap(range.s + delta), range.e - frameDuration);
            onChange(selected, {...range, s: Math.max(0, s)});
            onSeek(Math.max(0, s));
        } else {
            const end = Math.max(
                snap(range.e + delta),
                range.s + frameDuration
            );
            onChange(selected, {...range, e: Math.min(duration, end)});
            onSeek(
                Math.max(range.s, snap(Math.min(duration, end) - frameDuration))
            );
        }
    };

    const percent = (time: number) =>
        Math.min(101, Math.max(-1, timeToPercent(time, view)));
    const visible = (r: ClipTimeRange) =>
        r.e >= view.start && r.s <= view.start + view.span;

    const background =
        isVideo && videoSrc && cache ? (
            <VideoFilmstrip
                src={videoSrc}
                duration={duration}
                view={view}
                frameDuration={frameDuration}
                cache={cache}
                aspect={aspect}
                className="absolute inset-0"
            />
        ) : peaks ? (
            <div className="absolute inset-0 overflow-hidden bg-muted">
                <AudioWaveform peaks={peaks} view={view} duration={duration} />
            </div>
        ) : null;

    const precision =
        ticks.minor.unit === 'frames'
            ? t('clip.frame_count', '{{count}} frames', {
                  count: ticks.minor.count,
              })
            : formatStepDuration(ticks.minor.seconds);
    const hoverPercent = hover !== undefined ? timeToPercent(hover, view) : 0;

    return (
        <div className="space-y-1.5">
            <div
                ref={rootRef}
                role="group"
                aria-label={t('clip.timeline', 'Timeline')}
                tabIndex={0}
                className="relative rounded-md border outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onKeyDown={onKeyDown}
                onPointerDown={() =>
                    rootRef.current?.focus({preventScroll: true})
                }
                data-testid="clip-range-timeline"
            >
                <TimelineRuler
                    view={view}
                    ticks={ticks}
                    currentTime={currentTime}
                    {...rulerHandlers}
                />
                <div
                    ref={laneRef}
                    className={cn(
                        'relative touch-none overflow-hidden select-none',
                        background ? 'h-14' : 'h-8',
                        canCreate ? 'cursor-crosshair' : 'cursor-default'
                    )}
                    data-testid="clip-timeline-track"
                    {...handlers}
                >
                    {background}
                    {ranges.map((r, i) =>
                        visible(r) ? (
                            <button
                                // The ranges are edited in place: their index is their identity
                                key={i}
                                type="button"
                                aria-label={formatClipRange(r)}
                                aria-pressed={i === selected}
                                title={formatClipRange(r)}
                                data-drag="range"
                                data-index={i}
                                className={cn(
                                    'absolute inset-y-0 cursor-grab rounded-sm border-2 outline-none focus-visible:ring-2 focus-visible:ring-ring active:cursor-grabbing',
                                    i === selected
                                        ? 'border-primary bg-primary/35'
                                        : 'border-primary/60 bg-primary/20 hover:bg-primary/30',
                                    invalid.includes(i) &&
                                        'border-destructive bg-destructive/20'
                                )}
                                style={{
                                    left: `${percent(r.s)}%`,
                                    width: `${Math.max(0.3, percent(r.e) - percent(r.s))}%`,
                                }}
                                onClick={e => {
                                    // A pointer click is handled by the drag
                                    if (e.detail === 0) {
                                        onSelect(i);
                                    }
                                }}
                            />
                        ) : null
                    )}
                    {range ? (
                        <>
                            <RangeHandle
                                edge="start"
                                time={range.s}
                                min={0}
                                max={range.e}
                                left={percent(range.s)}
                                hidden={!isInView(view, range.s)}
                                index={selected}
                                label={t(
                                    'clip.range_start_handle',
                                    'Start of the range'
                                )}
                                onKeyDown={e => onHandleKeyDown(e, 's')}
                            />
                            <RangeHandle
                                edge="end"
                                time={range.e}
                                min={range.s}
                                max={duration}
                                left={percent(range.e)}
                                hidden={!isInView(view, range.e)}
                                index={selected}
                                label={t(
                                    'clip.range_end_handle',
                                    'End of the range'
                                )}
                                onKeyDown={e => onHandleKeyDown(e, 'e')}
                            />
                        </>
                    ) : null}
                    {draft ? (
                        <div
                            className="pointer-events-none absolute inset-y-0 rounded-sm border-2 border-dashed border-primary bg-primary/25"
                            style={{
                                left: `${percent(draft.s)}%`,
                                width: `${Math.max(0.3, percent(draft.e) - percent(draft.s))}%`,
                            }}
                            data-testid="clip-range-draft"
                        />
                    ) : null}
                    {isInView(view, currentTime) ? (
                        <div
                            className={cn(
                                'pointer-events-none absolute inset-y-0 w-px bg-foreground',
                                // Kept visible on the thumbnails
                                background &&
                                    'shadow-[0_0_0_1px_var(--background)]'
                            )}
                            style={{
                                left: `${timeToPercent(currentTime, view)}%`,
                            }}
                        />
                    ) : null}
                    {hover !== undefined && !dragging ? (
                        <div
                            className="pointer-events-none absolute inset-y-0 w-px bg-foreground/40"
                            style={{left: `${hoverPercent}%`}}
                        />
                    ) : null}
                </div>
                {hover !== undefined && !dragging ? (
                    <TimelineHoverPreview
                        time={hover}
                        frame={
                            isVideo
                                ? frameIndex(hover, frameDuration)
                                : undefined
                        }
                        preview={isVideo ? preview : undefined}
                        aspect={aspect}
                        percent={hoverPercent}
                    />
                ) : null}
            </div>
            <TimelineOverview
                duration={duration}
                view={view}
                ranges={ranges}
                selected={selected}
                currentTime={currentTime}
                label={t(
                    'clip.overview',
                    'Whole duration: drag the window to scroll'
                )}
                onViewStart={scrollTo}
            />
            <div className="flex flex-wrap items-center gap-2">
                <span
                    className="rounded bg-muted px-2 py-0.5 font-mono text-xs text-muted-foreground tabular-nums"
                    data-testid="clip-zoom-precision"
                >
                    {t('clip.precision', '1 graduation = {{value}}', {
                        value: precision,
                    })}
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => zoom(1 / ZOOM_STEP)}
                    disabled={view.span >= duration}
                    aria-label={t('clip.zoom_out', 'Zoom out')}
                    title={t('clip.zoom_out', 'Zoom out')}
                    data-testid="clip-zoom-out"
                >
                    <ZoomOutIcon />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => zoom(ZOOM_STEP)}
                    disabled={view.span <= Math.min(minSpan, duration)}
                    aria-label={t('clip.zoom_in', 'Zoom in')}
                    title={t('clip.zoom_in', 'Zoom in')}
                    data-testid="clip-zoom-in"
                >
                    <ZoomInIcon />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={fit}
                    disabled={view.span >= duration}
                    aria-label={t('clip.zoom_fit', 'See the whole duration')}
                    title={t('clip.zoom_fit', 'See the whole duration')}
                    data-testid="clip-zoom-fit"
                >
                    <MaximizeIcon />
                </Button>
                {frameRate ? (
                    <FrameRateSelect state={frameRate} className="ml-auto" />
                ) : null}
            </div>
            <p className="text-xs text-muted-foreground">
                {t(
                    'clip.timeline_help',
                    'Drag on the timeline to add a range, drag its edges to resize it. Keys: ←/→ one frame (Shift: one second), I/O start/end at the playhead, +/− zoom, 0 whole duration; Ctrl + wheel zooms.'
                )}
            </p>
        </div>
    );
}
