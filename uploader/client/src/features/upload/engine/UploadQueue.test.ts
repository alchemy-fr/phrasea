import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/lib/api/http';
import {UploadQueue, type UploadFileFn} from './UploadQueue';

type Deferred = {
    file: File;
    signal: AbortSignal;
    onProgress: (loaded: number) => void;
    resolve: (assetId: string) => void;
    reject: (e: unknown) => void;
};

function setup(options: {online?: boolean; maxAttempts?: number} = {}) {
    const calls: Deferred[] = [];
    let online = options.online ?? true;
    let onlineCallback: () => void = () => undefined;
    const uploadFile: UploadFileFn = (file, {signal, onProgress}) =>
        new Promise((resolve, reject) => {
            calls.push({
                file,
                signal,
                onProgress,
                resolve: assetId => resolve({assetId}),
                reject,
            });
            signal.addEventListener('abort', () =>
                reject(new DOMException('Aborted', 'AbortError'))
            );
        });
    const onError = vi.fn();
    const queue = new UploadQueue({
        uploadFile,
        maxAttempts: options.maxAttempts ?? 3,
        isOnline: () => online,
        retryDelay: () => 10,
        onError,
    });
    queue.watchOnline(cb => {
        onlineCallback = cb;

        return () => undefined;
    });

    return {
        queue,
        calls,
        onError,
        setOnline: (v: boolean) => {
            online = v;
            if (v) {
                onlineCallback();
            }
        },
    };
}

const file = (name: string, size = 10) =>
    new File(['x'.repeat(size)], name, {type: 'image/png'});

const flush = () => vi.advanceTimersByTimeAsync(0);

describe('UploadQueue', () => {
    beforeEach(() => {
        vi.useFakeTimers({toFake: ['setTimeout', 'clearTimeout']});
    });
    afterEach(() => {
        vi.useRealTimers();
    });

    it('sends two files at a time', async () => {
        const {queue, calls} = setup();
        queue.add([file('a'), file('b'), file('c')]);
        expect(calls.map(c => c.file.name)).toEqual(['a', 'b']);

        calls[0].resolve('asset-a');
        await vi.runAllTimersAsync();
        expect(calls.map(c => c.file.name)).toEqual(['a', 'b', 'c']);
    });

    it('is complete once every file is uploaded', async () => {
        const {queue, calls} = setup();
        queue.add([file('a', 4), file('b', 6)]);
        calls[0].onProgress(2);
        expect(queue.getSnapshot()).toMatchObject({
            loaded: 2,
            total: 10,
            complete: false,
        });
        calls[0].resolve('asset-a');
        calls[1].resolve('asset-b');
        await vi.runAllTimersAsync();
        const snapshot = queue.getSnapshot();
        expect(snapshot.complete).toBe(true);
        expect(snapshot.loaded).toBe(10);
        expect(snapshot.items.map(i => i.assetId)).toEqual([
            'asset-a',
            'asset-b',
        ]);
    });

    it('does not retry a file the API refused, and frees its slot', async () => {
        const {queue, calls, onError} = setup();
        queue.add([file('a'), file('b'), file('c')]);
        calls[0].reject(new ApiError('Invalid file', 400, {}));
        await vi.runAllTimersAsync();

        const [a] = queue.getSnapshot().items;
        expect(a).toMatchObject({status: 'error', error: 'Invalid file'});
        expect(onError).toHaveBeenCalledOnce();
        // The third file took the free slot
        expect(calls.map(c => c.file.name)).toEqual(['a', 'b', 'c']);
        expect(queue.getSnapshot().failed).toBe(1);
    });

    it('does not count what was sent of a failed file', async () => {
        const {queue, calls} = setup();
        queue.add([file('a', 10)]);
        calls[0].onProgress(10);
        expect(queue.getSnapshot().loaded).toBe(10);
        calls[0].reject(new ApiError('Nope', 400, {}));
        await vi.runAllTimersAsync();
        expect(queue.getSnapshot().loaded).toBe(0);
    });

    it('retries a failing file, then gives up', async () => {
        const {queue, calls} = setup({maxAttempts: 3});
        queue.add([file('a')]);
        calls[0].reject(new Error('Network error'));
        await flush();
        expect(queue.getSnapshot().items[0].status).toBe('retrying');
        await vi.runAllTimersAsync();
        calls[1].reject(new Error('Network error'));
        await vi.runAllTimersAsync();
        calls[2].reject(new Error('Network error'));
        await vi.runAllTimersAsync();

        expect(calls).toHaveLength(3);
        expect(queue.getSnapshot().items[0]).toMatchObject({
            status: 'error',
            attempts: 3,
        });
    });

    it('sends a failed file again on demand', async () => {
        const {queue, calls} = setup();
        queue.add([file('a')]);
        calls[0].reject(new ApiError('Nope', 400, {}));
        await vi.runAllTimersAsync();
        const [{id}] = queue.getSnapshot().items;

        queue.retry(id);
        expect(calls).toHaveLength(2);
        calls[1].resolve('asset-a');
        await vi.runAllTimersAsync();
        expect(queue.getSnapshot().complete).toBe(true);
    });

    it('completes once the failed file is removed', async () => {
        const {queue, calls} = setup();
        queue.add([file('a'), file('b')]);
        calls[0].reject(new ApiError('Nope', 400, {}));
        calls[1].resolve('asset-b');
        await vi.runAllTimersAsync();
        expect(queue.getSnapshot().complete).toBe(false);

        queue.remove(queue.getSnapshot().items[0].id);
        expect(queue.getSnapshot()).toMatchObject({complete: true, failed: 0});
    });

    it('sends every failed file again on demand', async () => {
        const {queue, calls} = setup();
        queue.add([file('a'), file('b'), file('c')]);
        calls[0].reject(new ApiError('Nope', 400, {}));
        calls[1].reject(new ApiError('Nope', 400, {}));
        await vi.runAllTimersAsync();
        calls[2].resolve('asset-c');
        await vi.runAllTimersAsync();
        expect(queue.getSnapshot().failed).toBe(2);

        queue.retryFailed();
        expect(queue.getSnapshot().failed).toBe(0);
        expect(calls.map(c => c.file.name)).toEqual(['a', 'b', 'c', 'a', 'b']);
    });

    it('drops every failed file at once', async () => {
        const {queue, calls} = setup();
        queue.add([file('a'), file('b')]);
        calls[0].reject(new ApiError('Nope', 400, {}));
        calls[1].resolve('asset-b');
        await vi.runAllTimersAsync();

        queue.removeFailed();
        expect(queue.getSnapshot()).toMatchObject({complete: true, failed: 0});
        expect(queue.getSnapshot().items.map(i => i.file.name)).toEqual(['b']);
    });

    it('waits for the connection to come back', async () => {
        const {queue, calls, setOnline} = setup();
        queue.add([file('a')]);
        setOnline(false);
        calls[0].reject(new Error('Network error'));
        await vi.runAllTimersAsync();
        expect(queue.getSnapshot().items[0].status).toBe('offline');
        expect(calls).toHaveLength(1);

        setOnline(true);
        expect(calls).toHaveLength(2);
        calls[1].resolve('asset-a');
        await vi.runAllTimersAsync();
        expect(queue.getSnapshot().complete).toBe(true);
    });

    it('stops every transfer on cancel, without reporting errors', async () => {
        const {queue, calls, onError} = setup();
        queue.add([file('a'), file('b')]);
        queue.cancel();
        await vi.runAllTimersAsync();

        expect(calls.every(c => c.signal.aborted)).toBe(true);
        expect(queue.getSnapshot().items).toEqual([]);
        expect(onError).not.toHaveBeenCalled();
    });

    it('stops the transfer of a removed file', async () => {
        const {queue, calls} = setup();
        queue.add([file('a'), file('b'), file('c')]);
        queue.remove(queue.getSnapshot().items[0].id);
        expect(calls[0].signal.aborted).toBe(true);
        expect(calls.map(c => c.file.name)).toEqual(['a', 'b', 'c']);
    });

    it('keeps the same snapshot until something changes', () => {
        const {queue} = setup();
        expect(queue.getSnapshot()).toBe(queue.getSnapshot());
    });
});
