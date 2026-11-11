import {afterEach, describe, expect, it, vi} from 'vitest';
import {act, cleanup, fireEvent, render, screen} from '@testing-library/react';
import {
    type MediaPlaybackControl,
    MediaPlaybackProvider,
    useRegisterMediaPlayback,
} from '@/features/assets/player/MediaPlaybackProvider';
import {WebVttTranscript} from './WebVttTranscript';

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

const vtt = [
    'WEBVTT',
    '00:00:01.000 --> 00:00:04.000\n<v Mary>Hello there',
    '00:00:05.000 --> 00:00:09.000\nSecond line',
    '00:01:10.000 --> 00:01:12.000\nThird line',
].join('\n\n');

function fakeControl() {
    const listeners = new Set<() => void>();
    const control = {
        time: 0,
        playFrom: vi.fn(),
        currentTime: () => control.time,
        subscribe: (listener: () => void) => {
            listeners.add(listener);

            return () => {
                listeners.delete(listener);
            };
        },
        emit: (t: number) => {
            control.time = t;
            listeners.forEach(l => l());
        },
    };

    return control;
}

function Player({control}: {control: MediaPlaybackControl}) {
    useRegisterMediaPlayback(control);

    return null;
}

const rows = () =>
    Array.from(
        screen
            .getByTestId('webvtt-transcript')
            .querySelectorAll('[data-cue-index]')
    ) as HTMLElement[];
const current = () =>
    rows()
        .filter(r => r.getAttribute('aria-current') === 'true')
        .map(r => r.getAttribute('data-cue-index'));

describe('WebVttTranscript', () => {
    afterEach(cleanup);

    it('shows one line per cue, not clickable without a player', () => {
        render(<WebVttTranscript source={vtt} />);

        expect(rows().map(r => r.textContent)).toEqual([
            '0:01Mary Hello there',
            '0:05Second line',
            '1:10Third line',
        ]);
        expect(screen.queryAllByRole('button')).toEqual([]);
        expect(current()).toEqual([]);
    });

    it('is not clickable in an asset view without a player', () => {
        render(
            <MediaPlaybackProvider>
                <WebVttTranscript source={vtt} />
            </MediaPlaybackProvider>
        );

        expect(screen.queryAllByRole('button')).toEqual([]);
    });

    it('plays from the cue clicked, and follows the cue being played', () => {
        const control = fakeControl();
        render(
            <MediaPlaybackProvider>
                <Player control={control} />
                <WebVttTranscript source={vtt} />
            </MediaPlaybackProvider>
        );

        const buttons = screen.getAllByRole('button');
        expect(buttons.map(b => b.getAttribute('aria-label'))).toEqual([
            'Play from 0:01',
            'Play from 0:05',
            'Play from 1:10',
        ]);
        expect(current()).toEqual([]);

        fireEvent.click(buttons[1]);
        expect(control.playFrom).toHaveBeenCalledWith(5);

        act(() => control.emit(5.5));
        expect(current()).toEqual(['1']);

        act(() => control.emit(70));
        expect(current()).toEqual(['2']);

        act(() => control.emit(4.5));
        expect(current()).toEqual([]);
    });

    it('shows a value without any cue as is', () => {
        const {container} = render(
            <WebVttTranscript source="Not a WebVTT file" />
        );

        expect(screen.queryByTestId('webvtt-transcript')).toBeNull();
        expect(container.querySelector('pre')?.textContent).toBe(
            'Not a WebVTT file'
        );
    });

    it('marks the highlight of a search', () => {
        render(
            <WebVttTranscript
                source={vtt}
                highlight={vtt.replace('Second', '[hl]Second[/hl]')}
            />
        );

        const marks = Array.from(document.querySelectorAll('mark'));
        expect(marks.map(m => m.textContent)).toEqual(['Second']);
        expect(rows()[1].textContent).toBe('0:05Second line');
    });
});
