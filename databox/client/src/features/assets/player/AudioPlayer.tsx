'use client';

import {type RefObject, useEffect, useMemo, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {PauseIcon, PlayIcon} from 'lucide-react';
import {useWavesurfer} from '@wavesurfer/react';
import TimelinePlugin from 'wavesurfer.js/dist/plugins/timeline.esm.js';
import ZoomPlugin from 'wavesurfer.js/dist/plugins/zoom.esm.js';
import RegionsPlugin from 'wavesurfer.js/dist/plugins/regions.esm.js';
import type {ClipTimeRange} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {formatDuration} from '@/lib/utils/format';
import {cn} from '@alchemy/next-ui/utils/cn';
import {clipPlaybackTarget} from '@/features/clip/clip';
import {
    type MediaPlaybackControl,
    useRegisterMediaPlayback,
} from './MediaPlaybackProvider';

/**
 * Resolve a CSS color expression (may use `var(--token)` / `color-mix`) to a
 * value the canvas can draw with. Falls back to `fallback` when it cannot be
 * computed (SSR, jsdom…).
 */
function resolveCssColor(expr: string, fallback: string): string {
    if (typeof document === 'undefined') {
        return fallback;
    }
    const probe = document.createElement('span');
    probe.style.color = expr;
    probe.style.display = 'none';
    document.body.appendChild(probe);
    const value = getComputedStyle(probe).color;
    probe.remove();

    return value || fallback;
}

function formatTime(seconds: number): string {
    const formatted = formatDuration(seconds);

    return formatted.startsWith('00:') ? formatted.slice(3) : formatted;
}

/** Moves the sound to a time, in seconds, without playing it */
export type AudioSeek = (time: number) => void;

/**
 * Sound player rendering the waveform with wavesurfer.js. With `controls`, the
 * waveform is seekable, shows a timeline, can be zoomed (ctrl + wheel) and is
 * driven by a play/pause button and a `current / duration` counter; otherwise
 * it is a static waveform (grid thumbnails, unlocked hover preview).
 *
 * With `autoPlay`, playback follows the visibility of the player: it starts
 * when the waveform scrolls into view and pauses when it leaves. With
 * `playing`, playback is driven by the parent instead (thumbnails played on
 * hover): it rewinds when `playing` turns false.
 *
 * With `timeRanges` (a region of interest, the extract of a basket item),
 * the ranges are highlighted on the waveform and the playback is bounded to
 * them: it starts at the start of the first one, jumps from one to the next,
 * and stops (loops, when driven by `playing`) at the end of the last one.
 * With `boundPlayback` false, they are only highlighted; with `'playing'`,
 * the sound can be sought out of them while paused (an editor placing them),
 * and moves into them when played.
 *
 * With `syncPlayback`, it is the player of the asset view: it plays from the
 * cue of a transcript clicked in the side panel.
 *
 * With `seekRef`, the parent moves the sound (the timeline of an editor),
 * once it is loaded.
 *
 * The player fills the width of its box.
 */
export function AudioPlayer({
    src,
    autoPlay,
    playing,
    controls = true,
    height = 128,
    className,
    timeRanges,
    boundPlayback = true,
    syncPlayback = false,
    seekRef,
    onTimeUpdate,
    onDuration,
    onPeaks,
}: {
    src: string;
    autoPlay?: boolean;
    playing?: boolean;
    controls?: boolean;
    /** Waveform height in px */
    height?: number;
    className?: string;
    /** Sorted, not overlapping */
    timeRanges?: ClipTimeRange[];
    boundPlayback?: boolean | 'playing';
    /** Plays from the time of a transcript of the asset view */
    syncPlayback?: boolean;
    /** Set while the sound is loaded */
    seekRef?: RefObject<AudioSeek | null>;
    onTimeUpdate?: (time: number) => void;
    /** Called once the sound is loaded */
    onDuration?: (duration: number) => void;
    /**
     * Called once the sound is decoded, with its waveform: up to 1000 peaks
     * of the first channel, in [-1, 1]. Lets another view draw the sound
     * without downloading it again.
     */
    onPeaks?: (peaks: number[]) => void;
}) {
    const {t} = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const colors = useMemo(
        () => ({
            wave: resolveCssColor(
                'color-mix(in oklch, var(--primary) 50%, transparent)',
                'rgba(99, 102, 241, 0.5)'
            ),
            progress: resolveCssColor('var(--primary)', 'rgb(99, 102, 241)'),
            region: resolveCssColor(
                'color-mix(in oklch, var(--primary) 20%, transparent)',
                'rgba(99, 102, 241, 0.2)'
            ),
        }),
        []
    );
    // The ranges by value: a new array of the same ranges changes nothing
    const rangesKey = timeRanges?.length
        ? JSON.stringify(timeRanges)
        : undefined;
    const hasRange = rangesKey !== undefined;
    const regions = useMemo(
        () => (hasRange ? RegionsPlugin.create() : undefined),
        [hasRange]
    );
    const plugins = useMemo(
        () => [
            ...(controls
                ? [
                      TimelinePlugin.create(),
                      ZoomPlugin.create({scale: 0.5, maxZoom: 100}),
                  ]
                : []),
            ...(regions ? [regions] : []),
        ],
        [controls, regions]
    );
    const onTimeUpdateRef = useRef(onTimeUpdate);
    onTimeUpdateRef.current = onTimeUpdate;
    const onDurationRef = useRef(onDuration);
    onDurationRef.current = onDuration;
    const onPeaksRef = useRef(onPeaks);
    onPeaksRef.current = onPeaks;

    const {wavesurfer, currentTime, isReady, isPlaying} = useWavesurfer({
        container: containerRef,
        url: src,
        height,
        waveColor: colors.wave,
        progressColor: colors.progress,
        cursorColor: colors.progress,
        cursorWidth: controls ? 1 : 0,
        interact: controls,
        normalize: true,
        plugins,
    });

    const playbackControl = useMemo<MediaPlaybackControl | undefined>(
        () =>
            syncPlayback && wavesurfer && isReady
                ? {
                      playFrom: time => {
                          wavesurfer.setTime(time);
                          // Rejected when the browser blocks the playback
                          wavesurfer.play().catch(() => undefined);
                      },
                      currentTime: () => wavesurfer.getCurrentTime(),
                      subscribe: listener => {
                          const unsubscribe = [
                              wavesurfer.on('timeupdate', listener),
                              wavesurfer.on('seeking', listener),
                          ];

                          return () => unsubscribe.forEach(u => u());
                      },
                  }
                : undefined,
        [syncPlayback, wavesurfer, isReady]
    );
    useRegisterMediaPlayback(playbackControl);

    useEffect(() => {
        if (!seekRef || !wavesurfer || !isReady) {
            return;
        }
        seekRef.current = time => wavesurfer.setTime(time);

        return () => {
            seekRef.current = null;
        };
    }, [seekRef, wavesurfer, isReady]);

    // Autoplay follows visibility: play when shown, pause when scrolled away.
    useEffect(() => {
        const el = containerRef.current;
        if (!el || !wavesurfer || !autoPlay || !isReady) {
            return;
        }
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    void wavesurfer.play();
                } else if (wavesurfer.isPlaying()) {
                    wavesurfer.pause();
                }
            });
        });
        observer.observe(el);

        return () => observer.disconnect();
    }, [wavesurfer, autoPlay, isReady]);

    const rangeStart = timeRanges?.[0]?.s;
    const bounded = boundPlayback ? rangeStart : undefined;
    useEffect(() => {
        if (!wavesurfer || !isReady || undefined === playing) {
            return;
        }
        if (playing) {
            // Rejected when the browser blocks autoplay before any interaction
            wavesurfer.play().catch(() => undefined);
        } else {
            wavesurfer.pause();
            if (bounded !== undefined) {
                wavesurfer.setTime(bounded);
            } else {
                wavesurfer.seekTo(0);
            }
        }
    }, [wavesurfer, isReady, playing, bounded]);

    useEffect(() => {
        if (!wavesurfer) {
            return;
        }

        return wavesurfer.on('timeupdate', time =>
            onTimeUpdateRef.current?.(time)
        );
    }, [wavesurfer]);

    useEffect(() => {
        if (wavesurfer && isReady) {
            onDurationRef.current?.(wavesurfer.getDuration());
            if (onPeaksRef.current) {
                try {
                    onPeaksRef.current(
                        wavesurfer.exportPeaks({
                            channels: 1,
                            maxLength: 1000,
                        })[0] ?? []
                    );
                } catch {
                    // Not decoded: no waveform to share
                }
            }
        }
    }, [wavesurfer, isReady]);

    // The clip: highlighted, and bounding the playback
    const loop = undefined !== playing;
    useEffect(() => {
        if (!wavesurfer || !isReady || rangesKey === undefined) {
            return;
        }
        const ranges: ClipTimeRange[] = JSON.parse(rangesKey);

        regions?.clearRegions();
        for (const r of ranges) {
            regions?.addRegion({
                start: r.s,
                end: r.e,
                drag: false,
                resize: false,
                color: colors.region,
            });
        }
        if (!boundPlayback) {
            return;
        }

        // Sought out of the ranges while paused: left there
        const free = () =>
            boundPlayback === 'playing' && !wavesurfer.isPlaying();
        const moveIn = (time: number) => {
            const target = clipPlaybackTarget(time, ranges);
            if (target) {
                wavesurfer.setTime(target.time);
            }
        };
        if (!free()) {
            moveIn(wavesurfer.getCurrentTime());
        }

        const unsubscribe = [
            wavesurfer.on('timeupdate', time => {
                if (free()) {
                    return;
                }
                const target = clipPlaybackTarget(time, ranges);
                if (!target) {
                    return;
                }
                if (!target.ended || loop) {
                    wavesurfer.setTime(target.time);
                } else {
                    wavesurfer.pause();
                }
            }),
            wavesurfer.on('play', () => moveIn(wavesurfer.getCurrentTime())),
            wavesurfer.on('interaction', time => {
                if (!free()) {
                    moveIn(time);
                }
            }),
        ];

        return () => unsubscribe.forEach(u => u());
    }, [wavesurfer, isReady, regions, rangesKey, boundPlayback, loop, colors]);

    const duration = wavesurfer && isReady ? wavesurfer.getDuration() : null;
    const playLabel = isPlaying
        ? t('player.pause', 'Pause')
        : t('player.play', 'Play');

    return (
        <div
            className={cn(
                'flex w-full items-center justify-center',
                controls && 'p-4',
                className
            )}
            data-testid="audio-player"
            data-playing={isPlaying || undefined}
        >
            <div className="flex w-full flex-col gap-2">
                <div
                    className="relative w-full overflow-hidden"
                    style={{minHeight: height}}
                >
                    <div
                        ref={containerRef}
                        className={cn('w-full', controls && 'cursor-pointer')}
                        aria-label={t('player.audio', 'Audio player')}
                    />
                    {!isReady ? (
                        <div className="absolute inset-0 flex items-center justify-center">
                            <Spinner />
                        </div>
                    ) : null}
                </div>
                {controls ? (
                    <div className="flex items-center justify-between gap-3 px-1">
                        <Button
                            variant="secondary"
                            size="icon"
                            onClick={() => void wavesurfer?.playPause()}
                            disabled={!wavesurfer || !isReady}
                            aria-label={playLabel}
                            title={playLabel}
                        >
                            {isPlaying ? <PauseIcon /> : <PlayIcon />}
                        </Button>
                        <div className="font-mono text-sm tabular-nums text-muted-foreground">
                            {duration !== null ? (
                                <>
                                    {formatTime(currentTime)} /{' '}
                                    {formatTime(duration)}
                                </>
                            ) : (
                                <span className="inline-block h-4 w-20 animate-pulse rounded bg-muted" />
                            )}
                        </div>
                    </div>
                ) : null}
            </div>
        </div>
    );
}
