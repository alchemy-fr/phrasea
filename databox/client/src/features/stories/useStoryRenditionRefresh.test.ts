import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QueryClient} from '@tanstack/react-query';
import type {Asset} from '@/types/api';
import {useAssetStore} from '@/features/assets/assetStore';

const api = vi.hoisted(() => ({
    getAsset: vi.fn(async (id: string) => ({id, name: `${id} v2`})),
}));
vi.mock('@/lib/api/assets', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...api,
}));

const {refreshStoriesOf} = await import('./useStoryRenditionRefresh');

function asset(id: string, extra: Partial<Asset> = {}): Asset {
    return {id, name: id, ...extra} as unknown as Asset;
}

function storyPages(items: Asset[]) {
    return {
        pages: [{items, total: items.length}],
        pageParams: [undefined],
    };
}

describe('refreshStoriesOf', () => {
    let queryClient: QueryClient;
    let reloadAsset: ReturnType<typeof vi.fn<(id: string) => Promise<void>>>;

    beforeEach(() => {
        queryClient = new QueryClient();
        reloadAsset = vi.fn(async (_id: string) => undefined);
        api.getAsset.mockClear();
        useAssetStore.setState({assets: {}, reloadAsset});
    });

    it('refreshes the stories of a displayed asset', async () => {
        useAssetStore.setState({
            assets: {
                a1: asset('a1', {
                    collections: [
                        {id: 'c1'},
                        {id: 'c2', storyAsset: asset('s1')},
                    ] as Asset['collections'],
                }),
            },
        });
        queryClient.setQueryData(['story-thumbnails', 's1'], {thumbnails: []});
        queryClient.setQueryData(['story-thumbnails', 's2'], {thumbnails: []});

        await refreshStoriesOf(queryClient, ['a1']);

        expect(
            queryClient.getQueryState(['story-thumbnails', 's1'])?.isInvalidated
        ).toBe(true);
        expect(
            queryClient.getQueryState(['story-thumbnails', 's2'])?.isInvalidated
        ).toBe(false);
        expect(reloadAsset).toHaveBeenCalledWith('s1');
        expect(api.getAsset).not.toHaveBeenCalled();
    });

    it('updates the item in the browsed stories', async () => {
        queryClient.setQueryData(
            ['story-assets', 's1'],
            storyPages([asset('a1'), asset('a2')])
        );
        queryClient.setQueryData(
            ['story-assets', 's2'],
            storyPages([asset('a3')])
        );

        await refreshStoriesOf(queryClient, ['a2']);

        expect(api.getAsset).toHaveBeenCalledTimes(1);
        expect(
            queryClient
                .getQueryData<
                    ReturnType<typeof storyPages>
                >(['story-assets', 's1'])
                ?.pages[0].items.map(a => a.name)
        ).toEqual(['a1', 'a2 v2']);
        expect(reloadAsset).toHaveBeenCalledWith('s1');
        expect(reloadAsset).not.toHaveBeenCalledWith('s2');
    });
});
