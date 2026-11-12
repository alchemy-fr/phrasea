import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {useEffect, type ReactNode} from 'react';
import {act, cleanup, fireEvent, render, screen} from '@testing-library/react';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {
    AnnotationsProvider,
    useAnnotations,
    type AnnotationTarget,
} from '@/features/annotations/AnnotationsProvider';
import {
    AnnotationType,
    type AssetAnnotation,
} from '@/features/annotations/types';
import {VideoJsPlayer} from './VideoJsPlayer';
import type {SubtitleTrack} from './subtitles';
import {MediaPlaybackProvider, useMediaPlayback} from './MediaPlaybackProvider';

vi.mock('react-i18next', () => ({
    useTranslation: () => ({
        t: (
            _key: string,
            defaultValue: string,
            options?: Record<string, unknown>
        ) =>
            defaultValue.replace(/{{(\w+)}}/g, (_m, name) =>
                String(options?.[name])
            ),
        i18n: {language: 'en'},
    }),
}));

// The controls of Video.js are its own: only the structure the player fills
// (the container, the time slider) is rendered
vi.mock('@videojs/react', async () => {
    const {forwardRef} = await import('react');
    type Props = {children?: ReactNode; className?: string};
    const Box = ({children, className}: Props) => (
        <div className={className}>{children}</div>
    );
    const Pass = ({children}: Props) => <>{children}</>;
    const Nothing = () => null;

    return {
        CaptionsRadioGroup: {Root: Pass, Options: Nothing},
        Container: forwardRef<HTMLDivElement, Props>(function Container(
            {children, ...props},
            ref
        ) {
            return (
                <div ref={ref} {...props}>
                    {children}
                </div>
            );
        }),
        Controls: {Root: Pass, Content: Box},
        FullscreenButton: Nothing,
        Gesture: Nothing,
        Hotkey: Nothing,
        Menu: {
            Root: Pass,
            Trigger: Nothing,
            Popup: Nothing,
            Content: Pass,
            RadioItem: Pass,
            ItemIndicator: Nothing,
        },
        MuteButton: Nothing,
        PlayButton: Nothing,
        PlaybackRateButton: Nothing,
        Time: {Group: Box, Value: Nothing, Separator: Nothing},
        TimeSlider: {
            Root: ({children}: Props) => (
                <div data-testid="time-slider">{children}</div>
            ),
            Track: Nothing,
            Buffer: Nothing,
            Fill: Nothing,
            Thumb: Nothing,
            Preview: Nothing,
            Value: Nothing,
        },
        VolumeSlider: {
            Root: Nothing,
            Track: Nothing,
            Fill: Nothing,
            Thumb: Nothing,
        },
    };
});
vi.mock('@videojs/react/i18n', () => ({
    I18nProvider: ({children}: {children?: ReactNode}) => <>{children}</>,
}));
vi.mock('@videojs/react/video', async () => {
    const {forwardRef} = await import('react');

    return {
        VideoPlayer: ({children}: {children?: ReactNode}) => <>{children}</>,
        usePlayer: () => undefined,
        Video: forwardRef<HTMLVideoElement, Record<string, unknown>>(
            function Video({children, ...props}, ref) {
                return (
                    <video ref={ref} data-testid="video" {...props}>
                        {children as ReactNode}
                    </video>
                );
            }
        ),
    };
});

/** The state of the video element, which jsdom does not play */
const media = {paused: true, currentTime: 0, duration: 20};
const pause = vi.fn(function (this: HTMLMediaElement) {
    media.paused = true;
    this.dispatchEvent(new Event('pause'));
});
const play = vi.fn(function (this: HTMLMediaElement) {
    media.paused = false;
    this.dispatchEvent(new Event('play'));

    return Promise.resolve();
});

function SetTarget({target}: {target?: AnnotationTarget}) {
    const ctx = useAnnotations();
    const setTarget = ctx?.setTarget;
    useEffect(() => {
        if (target) {
            setTarget?.(target);
        }
    }, [setTarget, target]);

    return null;
}

function renderPlayer({
    fallback,
    subtitles,
}: {fallback?: AnnotationTarget; subtitles?: SubtitleTrack[]} = {}) {
    const ui = (t?: AnnotationTarget) => (
        <TooltipPrimitive.Provider>
            <AnnotationsProvider fallback={fallback}>
                <SetTarget target={t} />
                <VideoJsPlayer
                    src="https://s3/video.mp4"
                    type="video/mp4"
                    subtitles={subtitles}
                    annotate
                />
            </AnnotationsProvider>
        </TooltipPrimitive.Provider>
    );
    const result = render(ui());

    return {
        ...result,
        setTarget: (t: AnnotationTarget) => result.rerender(ui(t)),
    };
}

const rect = (id: string, t?: number): AssetAnnotation => ({
    id,
    type: AnnotationType.Rect,
    x: 0.1,
    y: 0.1,
    w: 0.2,
    h: 0.2,
    ...(t === undefined ? {} : {t}),
});
const cue = (id: string, t: number): AssetAnnotation => ({
    id,
    type: AnnotationType.Cue,
    t,
});

const marker = (id: string) =>
    document.querySelector(
        `[data-annotation-marker="${id}"]`
    ) as HTMLButtonElement | null;

describe('VideoJsPlayer', () => {
    beforeEach(() => {
        media.paused = false;
        media.currentTime = 0;
        media.duration = 20;
        pause.mockClear();
        play.mockClear();
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                disconnect() {}
            }
        );
        vi.stubGlobal(
            'IntersectionObserver',
            class {
                observe() {}
                disconnect() {}
            }
        );
        const proto = HTMLVideoElement.prototype;
        Object.defineProperty(proto, 'paused', {
            configurable: true,
            get: () => media.paused,
        });
        Object.defineProperty(proto, 'duration', {
            configurable: true,
            get: () => media.duration,
        });
        Object.defineProperty(proto, 'currentTime', {
            configurable: true,
            get: () => media.currentTime,
            set(this: HTMLVideoElement, value: number) {
                media.currentTime = value;
                this.dispatchEvent(new Event('seeked'));
            },
        });
        Object.defineProperty(proto, 'pause', {
            configurable: true,
            value: pause,
        });
        Object.defineProperty(proto, 'play', {
            configurable: true,
            value: play,
        });
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        // Back to the members of HTMLMediaElement
        for (const name of [
            'paused',
            'duration',
            'currentTime',
            'pause',
            'play',
        ]) {
            Reflect.deleteProperty(HTMLVideoElement.prototype, name);
        }
        Reflect.deleteProperty(URL, 'createObjectURL');
        Reflect.deleteProperty(URL, 'revokeObjectURL');
    });

    it('offers the subtitles as tracks, the default one marked, revoked once gone', () => {
        let n = 0;
        const createObjectURL = vi.fn(() => `blob:track-${++n}`);
        const revokeObjectURL = vi.fn();
        // jsdom has neither
        Object.assign(URL, {createObjectURL, revokeObjectURL});
        const vtt = 'WEBVTT\n\n00:00.000 --> 00:05.000\nHello';
        const {unmount} = renderPlayer({
            subtitles: [
                {
                    id: 'en',
                    label: 'Transcript (English)',
                    locale: 'en',
                    src: vtt,
                },
                {
                    id: 'fr',
                    label: 'Transcript (French)',
                    locale: 'fr',
                    src: vtt,
                    default: true,
                },
            ],
        });

        const tracks = Array.from(document.querySelectorAll('track'));
        expect(
            tracks.map(t => ({
                kind: t.getAttribute('kind'),
                src: t.getAttribute('src'),
                lang: t.getAttribute('srclang'),
                label: t.getAttribute('label'),
                default: t.hasAttribute('default'),
            }))
        ).toEqual([
            {
                kind: 'subtitles',
                src: 'blob:track-1',
                lang: 'en',
                label: 'Transcript (English)',
                default: false,
            },
            {
                kind: 'subtitles',
                src: 'blob:track-2',
                lang: 'fr',
                label: 'Transcript (French)',
                default: true,
            },
        ]);
        const blob = createObjectURL.mock.calls[0] as unknown as [Blob];
        expect(blob[0].type).toBe('text/vtt');

        unmount();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:track-1');
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:track-2');
    });

    it('marks the timed annotations on the time slider, a click pauses on the frame', () => {
        renderPlayer({
            fallback: {
                key: 'asset',
                annotations: [rect('still'), rect('r5', 5), cue('c10', 10)],
            },
        });

        const slider = screen.getByTestId('time-slider');
        expect(
            Array.from(slider.querySelectorAll('[data-annotation-marker]')).map(
                m => [
                    m.getAttribute('data-annotation-marker'),
                    (m as HTMLElement).style.left,
                    m.getAttribute('aria-label'),
                ]
            )
        ).toEqual([
            ['r5', '25%', 'Rectangle · 0:05'],
            ['c10', '50%', 'Cue · 0:10'],
        ]);
        expect(pause).not.toHaveBeenCalled();

        fireEvent.click(marker('c10')!);

        expect(pause).toHaveBeenCalled();
        expect(media.currentTime).toBe(10);
        expect(marker('c10')!.getAttribute('data-selected')).toBe('true');
        expect(marker('r5')!.getAttribute('data-selected')).toBeNull();
    });

    it('pauses on the frame of the annotation selected from elsewhere', () => {
        const {setTarget} = renderPlayer();

        act(() =>
            setTarget({
                key: 'message-1',
                annotations: [rect('still'), rect('r5', 5), cue('c10', 10)],
                selectedId: 'r5',
            })
        );

        expect(pause).toHaveBeenCalled();
        expect(media.currentTime).toBe(5);
        expect(marker('r5')!.getAttribute('data-selected')).toBe('true');
    });

    it('pauses on the first timed annotation of a message shown without a selection', () => {
        const {setTarget} = renderPlayer();

        act(() =>
            setTarget({
                key: 'message-2',
                annotations: [rect('still'), cue('c12', 12), rect('r5', 5)],
            })
        );

        expect(pause).toHaveBeenCalled();
        expect(media.currentTime).toBe(12);
    });

    it('does not move for the annotations of the asset shown by default', () => {
        renderPlayer({
            fallback: {key: 'asset', annotations: [rect('r5', 5)]},
        });

        expect(pause).not.toHaveBeenCalled();
        expect(media.currentTime).toBe(0);
    });

    it('pauses when a drawing tool is taken, leaves it when playing', () => {
        const {setTarget} = renderPlayer();
        act(() =>
            setTarget({
                key: 'composer',
                annotations: [],
                editable: true,
                onChange: vi.fn(),
            })
        );
        const tool = () =>
            document.querySelector(
                `[data-tool="${AnnotationType.Rect}"]`
            ) as HTMLButtonElement;

        fireEvent.click(tool());

        expect(pause).toHaveBeenCalled();
        expect(tool().getAttribute('aria-pressed')).toBe('true');

        act(() => {
            media.paused = false;
            screen.getByTestId('video').dispatchEvent(new Event('play'));
        });
        expect(tool().getAttribute('aria-pressed')).toBe('false');
    });

    it('adds a cue at the current time', () => {
        const onChange = vi.fn();
        const {setTarget} = renderPlayer();
        act(() =>
            setTarget({
                key: 'composer',
                annotations: [],
                editable: true,
                onChange,
            })
        );
        media.currentTime = 7.5;

        fireEvent.click(
            document.querySelector(
                `[data-tool="${AnnotationType.Cue}"]`
            ) as HTMLButtonElement
        );

        expect(onChange).toHaveBeenLastCalledWith([
            expect.objectContaining({
                type: AnnotationType.Cue,
                t: 7.5,
                name: 'Cue #1',
            }),
        ]);
    });

    describe('as the player of the asset view', () => {
        function renderInView(annotate: boolean) {
            const seen: {ctx?: ReturnType<typeof useMediaPlayback>} = {};
            function Consumer() {
                const ctx = useMediaPlayback();
                useEffect(() => {
                    seen.ctx = ctx;
                });

                return null;
            }
            render(
                <TooltipPrimitive.Provider>
                    <AnnotationsProvider>
                        <MediaPlaybackProvider>
                            <VideoJsPlayer
                                src="https://s3/video.mp4"
                                type="video/mp4"
                                annotate={annotate}
                            />
                            <Consumer />
                        </MediaPlaybackProvider>
                    </AnnotationsProvider>
                </TooltipPrimitive.Provider>
            );

            return seen;
        }

        it('plays from the time asked for, and tells its time', () => {
            media.paused = true;
            const seen = renderInView(true);
            const listener = vi.fn();
            const unsubscribe = seen.ctx!.subscribeTime(listener);

            expect(seen.ctx?.available).toBe(true);
            act(() => seen.ctx?.playFrom(12));

            expect(media.currentTime).toBe(12);
            expect(play).toHaveBeenCalled();
            expect(pause).not.toHaveBeenCalled();
            expect(seen.ctx?.currentTime()).toBe(12);
            expect(listener).toHaveBeenCalled();

            act(() => seen.ctx?.playFrom(50));
            expect(media.currentTime).toBe(20);
            unsubscribe();
        });

        it('is not the player of the view without annotations', () => {
            const seen = renderInView(false);

            expect(seen.ctx?.available).toBe(false);
        });
    });
});
