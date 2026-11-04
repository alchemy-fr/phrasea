import {describe, expect, it, vi} from 'vitest';
import {mergeRefs} from './refs';

describe('mergeRefs', () => {
    it('feeds every ref, and nulls them on cleanup', () => {
        const object = {current: null as HTMLElement | null};
        const callback = vi.fn();
        const node = document.createElement('div');

        const cleanup = mergeRefs<HTMLElement>(object, callback, null)(node);

        expect(object.current).toBe(node);
        expect(callback).toHaveBeenCalledWith(node);

        (cleanup as () => void)();
        expect(object.current).toBeNull();
        expect(callback).toHaveBeenLastCalledWith(null);
    });

    it('runs the cleanup returned by a callback ref instead of nulling it', () => {
        const stop = vi.fn();
        const callback = vi.fn(() => stop);
        const node = document.createElement('div');

        const cleanup = mergeRefs<HTMLElement>(callback)(node);
        (cleanup as () => void)();

        expect(stop).toHaveBeenCalledOnce();
        expect(callback).toHaveBeenCalledTimes(1);
    });
});
