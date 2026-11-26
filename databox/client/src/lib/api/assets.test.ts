import {beforeEach, describe, expect, it, vi} from 'vitest';

const get = vi.fn();
const post = vi.fn();

vi.mock('./http', () => ({
    api: {
        get: (...args: unknown[]) => get(...args),
        post: (...args: unknown[]) => post(...args),
    },
}));

import {ASSET_IDS_CHUNK, getAssetsByIds, runQuarantineBatch} from './assets';

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

describe('runQuarantineBatch', () => {
    beforeEach(() => {
        post.mockReset();
    });

    const items = (count: number) => idList(count).map(id => ({id}));
    const processed = (ids: {id: string}[]) => ({
        results: ids.map(({id}) => ({id, status: 'processed'})),
        processed: ids.length,
        skipped: 0,
        failed: 0,
    });

    it('keeps the outcome of the slices run before a failing one', async () => {
        post.mockImplementationOnce((_url, body) =>
            Promise.resolve(processed(body.items))
        );
        post.mockRejectedValueOnce(new Error('Bad Gateway'));

        const result = await runQuarantineBatch({
            action: 'bypass',
            items: items(150),
        });

        expect(post).toHaveBeenCalledTimes(2);
        expect(post.mock.calls[1][1].items).toHaveLength(50);
        expect(result).toMatchObject({processed: 100, skipped: 0, failed: 50});
        expect(result.results).toHaveLength(150);
        expect(result.results[100]).toEqual({
            id: 'id-100',
            status: 'failed',
            reason: 'error',
        });
    });

    it('rejects when every slice failed', async () => {
        post.mockRejectedValue(new Error('Bad Gateway'));

        await expect(
            runQuarantineBatch({action: 'bypass', items: items(3)})
        ).rejects.toThrow('Bad Gateway');
    });
});
