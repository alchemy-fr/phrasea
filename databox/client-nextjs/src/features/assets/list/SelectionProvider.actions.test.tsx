import {describe, expect, it} from 'vitest';
import {act, renderHook} from '@testing-library/react';
import type {PropsWithChildren} from 'react';
import type {Asset} from '@/types/api';
import {
    SelectionProvider,
    selectionTargets,
    useSelectionActions,
} from './SelectionProvider';

const assets = ['a', 'b', 'c'].map(id => ({id}) as Asset);
const wrapper = ({children}: PropsWithChildren) => (
    <SelectionProvider>{children}</SelectionProvider>
);

describe('SelectionActions.getSelection', () => {
    it('reads the current selection without changing identity', () => {
        const {result} = renderHook(() => useSelectionActions(), {wrapper});
        const actions = result.current;
        expect(actions.getSelection()).toEqual([]);

        act(() => actions.onItemClick(assets[1], [assets]));
        expect(actions.getSelection().map(a => a.id)).toEqual(['b']);

        act(() => actions.selectAll([assets]));
        expect(actions.getSelection().map(a => a.id)).toEqual(['a', 'b', 'c']);
        expect(result.current).toBe(actions);

        act(() => actions.clear());
        expect(actions.getSelection()).toEqual([]);
    });
});

describe('selectionTargets', () => {
    it('stands for the whole selection when the item is selected', () => {
        expect(selectionTargets(assets[1], [assets[0], assets[1]])).toEqual([
            assets[0],
            assets[1],
        ]);
    });

    it('stands alone when the item is not selected', () => {
        expect(selectionTargets(assets[2], [assets[0]])).toEqual([assets[2]]);
        expect(selectionTargets(assets[2], [])).toEqual([assets[2]]);
    });
});
