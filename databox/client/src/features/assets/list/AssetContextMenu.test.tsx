import {describe, expect, it} from 'vitest';
import {act, renderHook} from '@testing-library/react';
import type {PropsWithChildren} from 'react';
import type {Asset} from '@/types/api';
import {SelectionProvider, useSelectionActions} from './SelectionProvider';
import {useSelectionMenuTargets} from './AssetContextMenu';

const [a, b] = ['a', 'b'].map(id => ({id}) as Asset);

function setup(disabledIds?: Set<string>) {
    const wrapper = ({children}: PropsWithChildren) => (
        <SelectionProvider disabledIds={disabledIds}>
            {children}
        </SelectionProvider>
    );

    return renderHook(
        () => ({
            selection: useSelectionActions(),
            menu: useSelectionMenuTargets(b),
        }),
        {wrapper}
    );
}

describe('useSelectionMenuTargets', () => {
    it('selects an unselected item alone and targets it only', () => {
        const {result} = setup();
        act(() => result.current.selection.onItemClick(a, [[a, b]]));

        act(() => result.current.menu.onOpenChange(true));
        expect(result.current.selection.getSelection()).toEqual([b]);
        expect(result.current.menu.targets).toEqual([b]);
    });

    it('keeps the selection as it is when the item is already selected', () => {
        const {result} = setup();
        act(() => result.current.selection.selectAll([[a, b]]));

        act(() => result.current.menu.onOpenChange(true));
        expect(result.current.selection.getSelection()).toEqual([a, b]);
        expect(result.current.menu.targets).toEqual([a, b]);
    });

    it('leaves the selection alone on a disabled item and targets it only', () => {
        const {result} = setup(new Set(['b']));
        act(() => result.current.selection.onItemClick(a, [[a, b]]));

        act(() => result.current.menu.onOpenChange(true));
        expect(result.current.selection.getSelection()).toEqual([a]);
        expect(result.current.menu.targets).toEqual([b]);
    });

    it('does nothing on close', () => {
        const {result} = setup();
        act(() => result.current.menu.onOpenChange(false));
        expect(result.current.selection.getSelection()).toEqual([]);
        expect(result.current.menu.targets).toEqual([b]);
    });
});
