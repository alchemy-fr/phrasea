import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {AssetThumb} from './AssetThumb';

const api = vi.hoisted(() => ({
    getStoryThumbnails: vi.fn(() =>
        Promise.resolve(['http://x/t1.jpg', 'http://x/t2.jpg'])
    ),
}));
vi.mock('@/lib/api/assets', () => api);
vi.mock('@/features/assets/player/AudioPlayer', () => ({
    AudioPlayer: ({controls}: {controls?: boolean}) => (
        <div data-testid="audio-player" data-controls={controls ? '1' : '0'} />
    ),
}));

const play = vi.fn(() => Promise.resolve());
const pause = vi.fn();
Object.defineProperty(HTMLMediaElement.prototype, 'play', {value: play});
Object.defineProperty(HTMLMediaElement.prototype, 'pause', {value: pause});

function fakeAsset(extra: Partial<Asset>): Asset {
    return {
        id: 'a1',
        name: 'Asset',
        capabilities: {},
        workspace: {id: 'ws'},
        ...extra,
    } as unknown as Asset;
}

function renderThumb(asset: Asset) {
    const i18n = createI18n('en');
    const client = new QueryClient();

    return render(
        <I18nextProvider i18n={i18n}>
            <QueryClientProvider client={client}>
                <AssetThumb asset={asset} size={200} />
            </QueryClientProvider>
        </I18nextProvider>
    );
}

describe('AssetThumb', () => {
    beforeEach(() => {
        play.mockClear();
        pause.mockClear();
        api.getStoryThumbnails.mockClear();
    });

    it('plays a video thumbnail only while hovered', () => {
        const {container} = renderThumb(
            fakeAsset({
                thumbnail: {
                    id: 'r',
                    file: {id: 'f', url: 'http://x/v.mp4', type: 'video/mp4'},
                } as any,
            })
        );
        const video = container.querySelector('video')!;
        expect(video).toBeTruthy();
        expect(video.hasAttribute('autoplay')).toBe(false);
        expect(play).not.toHaveBeenCalled();

        fireEvent.mouseEnter(container.firstElementChild!);
        expect(play).toHaveBeenCalledTimes(1);
        fireEvent.mouseLeave(container.firstElementChild!);
        expect(pause).toHaveBeenCalled();
    });

    it('shows a static waveform for an audio thumbnail', () => {
        const {container} = renderThumb(
            fakeAsset({
                thumbnail: {
                    id: 'r',
                    file: {id: 'f', url: 'http://x/a.mp3', type: 'audio/mp3'},
                } as any,
                source: {id: 's', type: 'audio/mp3', extension: 'mp3'} as any,
            })
        );
        expect(screen.getByTestId('audio-player').dataset.controls).toBe('0');
        expect(container.querySelector('img')).toBeNull();
    });

    it('loads and shows the story carousel on hover', async () => {
        const {container} = renderThumb(
            fakeAsset({
                storyCollection: {id: 'c'} as any,
                thumbnail: {
                    id: 'r',
                    file: {id: 'f', url: 'http://x/t.jpg', type: 'image/jpeg'},
                } as any,
            })
        );
        const strip = screen.getByTestId('story-thumb');
        expect(strip.className).toContain('opacity-0');
        expect(api.getStoryThumbnails).not.toHaveBeenCalled();

        fireEvent.mouseEnter(container.firstElementChild!);
        expect(strip.className).toContain('opacity-100');
        expect(api.getStoryThumbnails).toHaveBeenCalledWith('a1');
        await waitFor(() =>
            expect(strip.querySelectorAll('img')).toHaveLength(2)
        );
    });
});
