import {clearPromiseLocks, lockPromise} from '../../../src/lib/promise';

describe('lockPromise', () => {
    beforeEach(() => {
        clearPromiseLocks();
    });

    it('runs the handler once per key', async () => {
        const handler = vi.fn(async () => 'value');

        const [a, b] = await Promise.all([
            lockPromise('k', handler),
            lockPromise('k', handler),
        ]);

        expect(handler).toHaveBeenCalledTimes(1);
        expect(a).toEqual('value');
        expect(b).toEqual('value');
    });

    it('returns the very same promise for a given key', () => {
        const handler = async () => 'value';

        expect(lockPromise('k', handler)).toBe(lockPromise('k', handler));
    });

    it('keeps keys independent', async () => {
        const first = vi.fn(async () => 1);
        const second = vi.fn(async () => 2);

        expect(await lockPromise('a', first)).toEqual(1);
        expect(await lockPromise('b', second)).toEqual(2);
        expect(first).toHaveBeenCalledTimes(1);
        expect(second).toHaveBeenCalledTimes(1);
    });

    it('caches rejected promises too, so the handler is never retried', async () => {
        const handler = vi.fn(async () => {
            throw new Error('nope');
        });

        await expect(lockPromise('k', handler)).rejects.toThrow('nope');
        await expect(lockPromise('k', handler)).rejects.toThrow('nope');

        expect(handler).toHaveBeenCalledTimes(1);
    });

    it('is reset by clearPromiseLocks', async () => {
        const handler = vi.fn(async () => 'value');

        await lockPromise('k', handler);
        clearPromiseLocks();
        await lockPromise('k', handler);

        expect(handler).toHaveBeenCalledTimes(2);
    });
});
