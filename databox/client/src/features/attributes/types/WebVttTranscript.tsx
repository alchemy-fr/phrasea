'use client';

import {
    type KeyboardEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useSyncExternalStore,
} from 'react';
import {useTranslation} from 'react-i18next';
import {Highlight, stripHighlight} from '@/components/ui/highlight';
import {cn} from '@alchemy/next-ui/utils/cn';
import {formatAnnotationTime} from '@/features/annotations/annotationNames';
import {
    activeCueIndex,
    parseWebVtt,
    type WebVttCue,
} from '@/features/assets/player/webVtt';
import {useMediaPlayback} from '@/features/assets/player/MediaPlaybackProvider';

/** How long the automatic scroll waits after the user scrolled the list */
const userScrollPause = 4000;

const scrollKeys = new Set([
    'ArrowUp',
    'ArrowDown',
    'PageUp',
    'PageDown',
    'Home',
    'End',
]);

const noSubscription = () => () => {};

/**
 * A WebVTT value as a transcript: one line per cue, with its time. In an
 * asset view (a player registered), a line plays the media from its cue, the
 * cue being played is highlighted and kept visible. A value without any cue
 * is shown as is.
 */
export function WebVttTranscript({
    source,
    highlight,
}: {
    source: string;
    /** The value with the `[hl]` markers of a search */
    highlight?: string;
}) {
    const {t} = useTranslation();
    const cues = useMemo(
        () => parseWebVtt(highlight ?? source),
        [highlight, source]
    );
    const media = useMediaPlayback();
    const interactive = !!media?.available;
    const active = useSyncExternalStore(
        media?.subscribeTime ?? noSubscription,
        () => {
            const time = media?.currentTime();

            return time === undefined ? -1 : activeCueIndex(cues, time);
        },
        () => -1
    );

    const containerRef = useRef<HTMLDivElement>(null);
    const userScrolledAt = useRef(0);
    const pauseAutoScroll = useCallback(() => {
        userScrolledAt.current = Date.now();
    }, []);

    useEffect(() => {
        const container = containerRef.current;
        if (
            active < 0 ||
            !container ||
            Date.now() - userScrolledAt.current < userScrollPause
        ) {
            return;
        }
        const row = container.querySelector<HTMLElement>(
            `[data-cue-index="${active}"]`
        );
        if (!row) {
            return;
        }
        // The list only: scrolling the row into view would move the panel
        const top = row.offsetTop;
        const bottom = top + row.offsetHeight;
        const {scrollTop, clientHeight} = container;
        if (top < scrollTop || bottom > scrollTop + clientHeight) {
            container.scrollTop = Math.max(
                0,
                top - (clientHeight - row.offsetHeight) / 2
            );
        }
    }, [active]);

    if (cues.length === 0) {
        return (
            <pre className="max-h-60 overflow-auto rounded bg-muted p-2 font-mono text-xs">
                {stripHighlight(source)}
            </pre>
        );
    }

    return (
        <div
            ref={containerRef}
            data-testid="webvtt-transcript"
            className="relative max-h-80 overflow-auto motion-safe:scroll-smooth"
            onWheel={pauseAutoScroll}
            onTouchMove={pauseAutoScroll}
            onPointerDown={e => {
                // On the scrollbar
                if (e.target === e.currentTarget) {
                    pauseAutoScroll();
                }
            }}
            onKeyDown={(e: KeyboardEvent) => {
                if (scrollKeys.has(e.key)) {
                    pauseAutoScroll();
                }
            }}
        >
            {cues.map((cue, i) => {
                const current = i === active;
                const content = <CueContent cue={cue} />;
                const className = cn(
                    'flex w-full gap-2 rounded px-1.5 py-0.5 text-left',
                    current && 'bg-primary/10'
                );

                return interactive ? (
                    <button
                        key={i}
                        type="button"
                        data-cue-index={i}
                        aria-current={current ? 'true' : undefined}
                        aria-label={t(
                            'attribute.webvtt.play_from',
                            'Play from {{time}}',
                            {time: formatAnnotationTime(cue.start)}
                        )}
                        className={cn(
                            className,
                            'cursor-pointer outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring',
                            current && 'hover:bg-primary/15'
                        )}
                        onClick={() => {
                            userScrolledAt.current = 0;
                            media?.playFrom(cue.start);
                        }}
                    >
                        {content}
                    </button>
                ) : (
                    <div
                        key={i}
                        data-cue-index={i}
                        aria-current={current ? 'true' : undefined}
                        className={className}
                    >
                        {content}
                    </div>
                );
            })}
        </div>
    );
}

function CueContent({cue}: {cue: WebVttCue}) {
    return (
        <>
            <span className="shrink-0 text-muted-foreground tabular-nums">
                {formatAnnotationTime(cue.start)}
            </span>
            <span className="min-w-0 whitespace-pre-line">
                {cue.voice ? (
                    <span className="font-semibold">{cue.voice} </span>
                ) : null}
                <Highlight text={cue.text} />
            </span>
        </>
    );
}
