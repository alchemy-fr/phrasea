import {beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();

vi.mock('./http', () => ({api: {get: (...args: unknown[]) => get(...args)}}));

import {ASSET_IDS_CHUNK, getAssetsByIds} from './assets';

function idList(count: number): string[] {
    return Array.from({length: count}, (_, i) => `id-${i}`);
}

describe('getAssetsByIds', () => {
    beforeEach(() => {
        get.mockReset();
        get.mockImplementation(
            (_url: string, {params}: {params: {ids: string[]}}) =>
                Promise.resolve({
                    totalItems: params.ids.length,
                    member: params.ids.map(id => ({id})),
                })
        );
    });

    it('splits the ids into requests of at most one chunk', async () => {
        const ids = idList(ASSET_IDS_CHUNK * 2 + 3);

        const assets = await getAssetsByIds(ids);

        expect(get).toHaveBeenCalledTimes(3);
        const sent = get.mock.calls.map(c => c[1].params);
        expect(sent.map(p => p.ids.length)).toEqual([
            ASSET_IDS_CHUNK,
            ASSET_IDS_CHUNK,
            3,
        ]);
        sent.forEach(p => expect(p.limit).toBe(p.ids.length));
        expect(assets.map(a => a.id)).toEqual(ids);
    });

    it('applies the options to every chunk', async () => {
        const signal = new AbortController().signal;

        await getAssetsByIds(
            idList(ASSET_IDS_CHUNK + 1),
            {query: 'foo'},
            signal
        );

        get.mock.calls.forEach(c => {
            expect(c[1].params.query).toBe('foo');
            expect(c[1].signal).toBe(signal);
        });
    });

    it('sends no request without ids', async () => {
        expect(await getAssetsByIds([])).toEqual([]);
        expect(get).not.toHaveBeenCalled();
    });
});
