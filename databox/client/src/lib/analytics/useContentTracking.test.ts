import {beforeEach, describe, expect, it, vi} from 'vitest';
import {renderHook} from '@testing-library/react';

const matomo = vi.hoisted(() => ({
    isMediaTrackingEnabled: vi.fn(() => false),
    trackContentImpression: vi.fn(),
    trackContentInteraction: vi.fn(),
}));
vi.mock('./matomo', () => matomo);

const {contentClick, getContentTracking, useContentImpression} =
    await import('./useContentTracking');

type Tracked = {
    id: string;
    name?: string;
    trackingId?: string;
    resolvedTrackingId?: string;
};

beforeEach(() => {
    vi.clearAllMocks();
    matomo.isMediaTrackingEnabled.mockReturnValue(false);
});

describe('getContentTracking', () => {
    it('falls back like the API', () => {
        expect(
            getContentTracking({
                id: 'a',
                name: 'A',
                trackingId: 't',
                resolvedTrackingId: 'r',
            })
        ).toEqual({id: 'r', title: 'A'});
        expect(getContentTracking({id: 'a', trackingId: 't'})).toEqual({
            id: 't',
            title: undefined,
        });
        expect(getContentTracking({id: 'a'})).toEqual({
            id: 'a',
            title: undefined,
        });
    });
});

describe('useContentImpression', () => {
    const render = (asset: Tracked | undefined, type: string | undefined) =>
        renderHook(({a, tp}) => useContentImpression(a, tp), {
            initialProps: {a: asset, tp: type},
        });

    it('tracks an asset once, when the file is known', () => {
        const asset = {id: 'a', name: 'photo.jpg', resolvedTrackingId: 'r'};
        const {rerender} = render(asset, undefined);
        expect(matomo.trackContentImpression).not.toHaveBeenCalled();

        rerender({a: asset, tp: 'image/jpeg'});
        rerender({a: {...asset}, tp: 'image/png'});
        expect(matomo.trackContentImpression).toHaveBeenCalledTimes(1);
        expect(matomo.trackContentImpression).toHaveBeenCalledWith(
            'photo.jpg',
            'r'
        );

        rerender({a: {id: 'b', resolvedTrackingId: 'rb'}, tp: 'image/jpeg'});
        expect(matomo.trackContentImpression).toHaveBeenLastCalledWith(
            'rb',
            'rb'
        );
    });

    it('leaves a video to MediaAnalytics when enabled', () => {
        matomo.isMediaTrackingEnabled.mockReturnValue(true);
        render({id: 'v', resolvedTrackingId: 'r'}, 'video/mp4');
        render({id: 'm', resolvedTrackingId: 'r'}, 'audio/mpeg');

        expect(matomo.trackContentImpression).toHaveBeenCalledTimes(1);
        expect(matomo.trackContentImpression).toHaveBeenCalledWith('r', 'r');
    });

    it('tracks a video without MediaAnalytics', () => {
        render({id: 'v', name: 'clip', resolvedTrackingId: 'r'}, 'video/mp4');

        expect(matomo.trackContentImpression).toHaveBeenCalledWith('clip', 'r');
    });
});

describe('contentClick', () => {
    it('sends a click interaction', () => {
        contentClick({id: 'r', title: 'photo.jpg'});

        expect(matomo.trackContentInteraction).toHaveBeenCalledWith(
            'click',
            'photo.jpg',
            'r'
        );
    });
});
