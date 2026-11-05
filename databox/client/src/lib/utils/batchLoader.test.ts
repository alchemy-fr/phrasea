import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {createBatchLoader} from './batchLoader';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

const upper = async (keys: string[]) =>
    new Map(keys.filter(k => k !== 'missing').map(k => [k, k.toUpperCase()]));

describe('createBatchLoader', () => {
    it('loads the keys requested together in one call, once each', async () => {
        const load = vi.fn(upper);
        const get = createBatchLoader({load});

        const results = Promise.all([get('a'), get('b'), get('a')]);
        await vi.runAllTimersAsync();

        expect(await results).toEqual(['A', 'B', 'A']);
        expect(load).toHaveBeenCalledTimes(1);
        expect(load).toHaveBeenCalledWith(['a', 'b']);
    });

    it('splits the keys into calls of maxSize keys', async () => {
        const load = vi.fn(upper);
        const get = createBatchLoader({load, maxSize: 2});

        const results = Promise.all(['a', 'b', 'c'].map(get));
        await vi.runAllTimersAsync();

        expect(await results).toEqual(['A', 'B', 'C']);
        expect(load.mock.calls).toEqual([[['a', 'b']], [['c']]]);
    });

    it('starts a new batch after the previous one is sent', async () => {
        const load = vi.fn(upper);
        const get = createBatchLoader({load});

        const first = get('a');
        await vi.runAllTimersAsync();
        const second = get('b');
        await vi.runAllTimersAsync();

        expect([await first, await second]).toEqual(['A', 'B']);
        expect(load.mock.calls).toEqual([[['a']], [['b']]]);
    });

    it('resolves the keys left out by load to undefined', async () => {
        const get = createBatchLoader({load: upper});

        const results = Promise.all([get('a'), get('missing')]);
        await vi.runAllTimersAsync();

        expect(await results).toEqual(['A', undefined]);
    });

    it('rejects every key of a failed call', async () => {
        const error = new Error('boom');
        const get = createBatchLoader<string>({
            load: async () => {
                throw error;
            },
        });

        const results = Promise.allSettled([get('a'), get('b')]);
        await vi.runAllTimersAsync();

        expect(await results).toEqual([
            {status: 'rejected', reason: error},
            {status: 'rejected', reason: error},
        ]);
    });
});
