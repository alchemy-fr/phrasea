import {beforeEach, describe, expect, it} from 'vitest';

// Node ships its own (broken, file-backed) localStorage that shadows jsdom's
const storage = new Map<string, string>();
Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
        getItem: (k: string) => storage.get(k) ?? null,
        setItem: (k: string, v: string) => storage.set(k, v),
        removeItem: (k: string) => storage.delete(k),
        clear: () => storage.clear(),
    },
});

const {maxPinnedStories, togglePinned, usePinnedStoriesStore} =
    await import('./pinnedStoriesStore');

describe('togglePinned', () => {
    it('pins and unpins', () => {
        expect(togglePinned([], 'a')).toEqual(['a']);
        expect(togglePinned(['a'], 'b')).toEqual(['a', 'b']);
        expect(togglePinned(['a', 'b'], 'a')).toEqual(['b']);
    });

    it('drops the oldest pins past the limit', () => {
        const full = Array.from({length: maxPinnedStories}, (_, i) => `s${i}`);
        const next = togglePinned(full, 'new');
        expect(next).toHaveLength(maxPinnedStories);
        expect(next[0]).toBe('s1');
        expect(next[next.length - 1]).toBe('new');
    });
});

describe('usePinnedStoriesStore', () => {
    beforeEach(() => {
        window.localStorage.clear();
        usePinnedStoriesStore.setState({ids: []});
    });

    it('persists the pins in localStorage', () => {
        usePinnedStoriesStore.getState().toggle('a');
        usePinnedStoriesStore.getState().toggle('b');
        expect(
            JSON.parse(window.localStorage.getItem('dbx.pinned-stories')!)
        ).toEqual(['a', 'b']);

        usePinnedStoriesStore.getState().unpin('a');
        expect(usePinnedStoriesStore.getState().ids).toEqual(['b']);
        expect(
            JSON.parse(window.localStorage.getItem('dbx.pinned-stories')!)
        ).toEqual(['b']);
    });
});
