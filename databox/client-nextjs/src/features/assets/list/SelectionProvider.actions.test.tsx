import {describe, expect, it} from 'vitest';
import {act, renderHook} from '@testing-library/react';
import type {PropsWithChildren} from 'react';
import type {Asset} from '@/types/api';
import {SelectionProvider, useSelectionActions} from './SelectionProvider';

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
