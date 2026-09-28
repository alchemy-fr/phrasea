'use client';

import {useEffect, useMemo, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import {PauseIcon, PlayIcon} from 'lucide-react';
import {useWavesurfer} from '@wavesurfer/react';
import TimelinePlugin from 'wavesurfer.js/dist/plugins/timeline.esm.js';
import ZoomPlugin from 'wavesurfer.js/dist/plugins/zoom.esm.js';
import {Button} from '@/components/ui/button';
import {Spinner} from '@/components/ui/loader';
import {formatDuration} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';

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
 * The player fills the width of its box.
 */
export function AudioPlayer({
    src,
    autoPlay,
    playing,
    controls = true,
    height = 128,
    className,
}: {
    src: string;
    autoPlay?: boolean;
    playing?: boolean;
    controls?: boolean;
    /** Waveform height in px */
    height?: number;
    className?: string;
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
        }),
        []
    );
    const plugins = useMemo(
        () =>
            controls
                ? [
                      TimelinePlugin.create(),
                      ZoomPlugin.create({scale: 0.5, maxZoom: 100}),
                  ]
                : [],
        [controls]
    );

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

    useEffect(() => {
        if (!wavesurfer || !isReady || undefined === playing) {
            return;
        }
        if (playing) {
            // Rejected when the browser blocks autoplay before any interaction
            wavesurfer.play().catch(() => undefined);
        } else {
            wavesurfer.pause();
            wavesurfer.seekTo(0);
        }
    }, [wavesurfer, isReady, playing]);

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
