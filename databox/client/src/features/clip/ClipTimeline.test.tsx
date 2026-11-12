import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {cleanup, fireEvent, render, screen} from '@testing-library/react';
import type {ComponentProps} from 'react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ClipTimeline} from './ClipTimeline';

// Nothing to extract in jsdom
vi.mock('./VideoFilmstrip', () => ({
    VideoFilmstrip: () => <div data-testid="clip-filmstrip" />,
}));

/** Every element is 1000 px wide: 100 s shown, 10 px per second */
const WIDTH = 1000;

function renderTimeline(props: Partial<ComponentProps<typeof ClipTimeline>>) {
    const callbacks = {
        onChange: vi.fn(),
        onSelect: vi.fn(),
        onSeek: vi.fn(),
        onCreate: vi.fn(),
    };
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <ClipTimeline
                kind="video"
                ranges={[]}
                selected={-1}
                currentTime={0}
                duration={100}
                frameDuration={1 / 25}
                canCreate
                videoSrc="video.mp4"
                {...callbacks}
                {...props}
            />
        </I18nextProvider>
    );

    return {
        ...callbacks,
        root: screen.getByTestId('clip-range-timeline'),
        track: screen.getByTestId('clip-timeline-track'),
    };
}

function drag(
    target: HTMLElement,
    track: HTMLElement,
    from: number,
    to: number
) {
    fireEvent.pointerDown(target, {clientX: from, pointerId: 1, button: 0});
    fireEvent.pointerMove(track, {clientX: to, pointerId: 1});
    fireEvent.pointerUp(track, {clientX: to, pointerId: 1});
}

const precision = () => screen.getByTestId('clip-zoom-precision').textContent;

describe('ClipTimeline', () => {
    beforeEach(() => {
        vi.spyOn(
            HTMLElement.prototype,
            'getBoundingClientRect'
        ).mockReturnValue({
            x: 0,
            y: 0,
            left: 0,
            top: 0,
            right: WIDTH,
            bottom: 56,
            width: WIDTH,
            height: 56,
            toJSON: () => ({}),
        });
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('draws a range snapped to the frames', () => {
        const {track, onCreate, onSeek} = renderTimeline({
            ranges: [{s: 50, e: 60}],
            selected: 0,
        });
        // 12.31 s and 20.07 s: frames at 12.32 s and 20.08 s
        drag(track, track, 123.1, 200.7);

        expect(onCreate).toHaveBeenCalledWith({s: 12.32, e: 20.08});
        expect(onSeek).not.toHaveBeenCalled();
    });

    it('draws the range backwards too', () => {
        const {track, onCreate} = renderTimeline({});
        drag(track, track, 300, 100);

        expect(onCreate).toHaveBeenCalledWith({s: 10, e: 30});
    });

    it('moves the playhead on a click', () => {
        const {track, onCreate, onSeek} = renderTimeline({});
        drag(track, track, 300, 302);

        expect(onCreate).not.toHaveBeenCalled();
        expect(onSeek).toHaveBeenCalledWith(30.2);
    });

    it('scrubs instead of drawing past the limit', () => {
        const {track, onCreate, onSeek} = renderTimeline({canCreate: false});
        drag(track, track, 300, 400);

        expect(onCreate).not.toHaveBeenCalled();
        expect(onSeek).toHaveBeenLastCalledWith(40);
    });

    it('resizes the selected range by its handles', () => {
        const {track, onChange, onSeek} = renderTimeline({
            ranges: [{s: 50, e: 60}],
            selected: 0,
        });

        drag(screen.getByTestId('clip-range-handle-start'), track, 500, 452);
        expect(onChange).toHaveBeenLastCalledWith(0, {s: 45.2, e: 60});
        expect(onSeek).toHaveBeenLastCalledWith(45.2);

        // 65.51 s: the frame at 65.52 s
        drag(screen.getByTestId('clip-range-handle-end'), track, 600, 655.1);
        expect(onChange).toHaveBeenLastCalledWith(0, {s: 50, e: 65.52});
    });

    it('keeps the start before the end', () => {
        const {track, onChange} = renderTimeline({
            ranges: [{s: 50, e: 60}],
            selected: 0,
        });
        drag(screen.getByTestId('clip-range-handle-start'), track, 500, 800);

        const [index, range] = onChange.mock.lastCall!;
        expect(index).toBe(0);
        expect(range.s).toBeCloseTo(59.96);
        expect(range.e).toBe(60);
    });

    it('moves a range by its body, its length kept', () => {
        const {track, onChange, onSelect} = renderTimeline({
            ranges: [
                {s: 10, e: 20},
                {s: 50, e: 60},
            ],
            selected: 0,
        });
        const body = screen.getByRole('button', {name: '00:50–01:00'});
        drag(body, track, 550, 600);

        // Selected when moved
        expect(onSelect).toHaveBeenCalledWith(1);
        expect(onChange).toHaveBeenLastCalledWith(1, {s: 55, e: 65});

        // Bounded by the end of the media
        drag(body, track, 550, 990);
        expect(onChange).toHaveBeenLastCalledWith(1, {s: 90, e: 100});
    });

    it('selects a range on a click', () => {
        const {track, onChange, onSelect} = renderTimeline({
            ranges: [
                {s: 10, e: 20},
                {s: 50, e: 60},
            ],
            selected: 0,
        });
        drag(
            screen.getByRole('button', {name: '00:50–01:00'}),
            track,
            550,
            551
        );

        expect(onSelect).toHaveBeenCalledWith(1);
        expect(onChange).not.toHaveBeenCalled();
    });

    it('zooms with ctrl + wheel, down to the frame', () => {
        const {root} = renderTimeline({});
        expect(precision()).toBe('1 graduation = 1 s');

        // × 4: 25 s shown, 40 px per second
        fireEvent.wheel(root, {ctrlKey: true, deltaY: -300, clientX: 500});
        expect(precision()).toBe('1 graduation = 0.2 s');

        fireEvent.wheel(root, {ctrlKey: true, deltaY: -5000, clientX: 500});
        expect(precision()).toBe('1 graduation = 1 frame');

        fireEvent.click(screen.getByTestId('clip-zoom-fit'));
        expect(precision()).toBe('1 graduation = 1 s');
    });

    it('zooms with the buttons and the keys', () => {
        const {root} = renderTimeline({});
        fireEvent.click(screen.getByTestId('clip-zoom-in'));
        fireEvent.click(screen.getByTestId('clip-zoom-in'));
        expect(precision()).toBe('1 graduation = 0.2 s');

        fireEvent.keyDown(root, {key: '-'});
        expect(precision()).toBe('1 graduation = 0.5 s');

        fireEvent.keyDown(root, {key: '0'});
        expect(precision()).toBe('1 graduation = 1 s');
        expect(
            (screen.getByTestId('clip-zoom-out') as HTMLButtonElement).disabled
        ).toBe(true);
    });

    it('moves the playhead frame by frame with the arrows', () => {
        const {root, onSeek} = renderTimeline({currentTime: 10});

        fireEvent.keyDown(root, {key: 'ArrowRight'});
        expect(onSeek).toHaveBeenLastCalledWith(10.04);
        fireEvent.keyDown(root, {key: 'ArrowLeft'});
        expect(onSeek).toHaveBeenLastCalledWith(9.96);
        fireEvent.keyDown(root, {key: 'ArrowRight', shiftKey: true});
        expect(onSeek).toHaveBeenLastCalledWith(11);
    });

    it('sets the start and the end at the playhead with I and O', () => {
        const {root, onChange} = renderTimeline({
            ranges: [{s: 50, e: 60}],
            selected: 0,
            currentTime: 52.4,
        });

        fireEvent.keyDown(root, {key: 'i'});
        expect(onChange).toHaveBeenLastCalledWith(0, {s: 52.4, e: 60});
        fireEvent.keyDown(root, {key: 'o'});
        expect(onChange).toHaveBeenLastCalledWith(0, {s: 50, e: 52.4});
    });

    it('adds a range at the playhead with I without one', () => {
        const {root, onCreate, onChange} = renderTimeline({currentTime: 5});

        fireEvent.keyDown(root, {key: 'I'});
        expect(onCreate).toHaveBeenCalledWith();
        fireEvent.keyDown(root, {key: 'O'});
        expect(onChange).not.toHaveBeenCalled();
    });

    it('describes the handles', () => {
        renderTimeline({ranges: [{s: 50, e: 60}], selected: 0});
        const start = screen.getByTestId('clip-range-handle-start');

        expect(start.getAttribute('role')).toBe('slider');
        expect(start.getAttribute('aria-valuenow')).toBe('50');
        expect(start.getAttribute('aria-valuemax')).toBe('60');
        expect(start.getAttribute('aria-valuetext')).toBe('00:50.00');
    });

    it('offers to choose the frame rate', () => {
        renderTimeline({
            frameRate: {
                measured: 29.97,
                measuring: false,
                frameRate: 29.97,
                setOverride: vi.fn(),
            },
        });

        expect(screen.getByTestId('clip-frame-rate').textContent).toContain(
            'Auto (29.97 fps)'
        );
    });
});
