import {beforeEach, describe, expect, it} from 'vitest';
import {act, renderHook} from '@testing-library/react';
import {
    isEntityColumnVisible,
    SYNONYMS_OPTION,
    useHiddenEntityColumns,
} from './EntityColumnsMenu';

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

describe('isEntityColumnVisible', () => {
    it('hides the synonyms with their option or their locale', () => {
        const visible = (hidden: string[]) =>
            [
                'note',
                'translations.en',
                'translations.fr',
                'synonyms.en',
                'synonyms.fr',
            ].filter(id => isEntityColumnVisible(id, new Set(hidden)));

        expect(visible([])).toHaveLength(5);
        expect(visible(['note', 'translations.fr'])).toEqual([
            'translations.en',
            'synonyms.en',
        ]);
        expect(visible([SYNONYMS_OPTION])).toEqual([
            'note',
            'translations.en',
            'translations.fr',
        ]);
    });
});

describe('useHiddenEntityColumns', () => {
    beforeEach(() => window.localStorage.clear());

    it('remembers the hidden columns of each list', () => {
        const {result} = renderHook(() => useHiddenEntityColumns('l1'));
        act(() => result.current.toggle('usage', false));
        act(() => result.current.toggle('note', false));
        act(() => result.current.toggle('note', true));
        expect([...result.current.hidden]).toEqual(['usage']);

        expect([
            ...renderHook(() => useHiddenEntityColumns('l1')).result.current
                .hidden,
        ]).toEqual(['usage']);
        expect(
            renderHook(() => useHiddenEntityColumns('l2')).result.current.hidden
                .size
        ).toBe(0);
    });
});
