import {describe, expect, it, vi} from 'vitest';
import {renderHook} from '@testing-library/react';
import type {CollectionNode, Pager} from '../collectionStore';
import {isOnlyChildPager, mayHaveChildren, useAutoExpand} from './autoExpand';

function pager(ids: string[], extra: Partial<Pager> = {}): Pager {
    return {ids, loading: false, loaded: true, ...extra};
}

function node(children?: unknown[]): CollectionNode {
    return {
        id: 'c1',
        name: 'Col c1',
        workspaceId: 'w1',
        children,
    } as unknown as CollectionNode;
}

describe('isOnlyChildPager', () => {
    it('is false without a pager', () => {
        expect(isOnlyChildPager(undefined)).toBe(false);
    });

    it('is false while the children are not loaded', () => {
        expect(isOnlyChildPager(pager(['c1'], {loaded: false}))).toBe(false);
    });

    it('is false when more children remain to be loaded', () => {
        expect(
            isOnlyChildPager(pager(['c1'], {next: '/collections?page=2'}))
        ).toBe(false);
    });

    it('is true for exactly one loaded child', () => {
        expect(isOnlyChildPager(pager([]))).toBe(false);
        expect(isOnlyChildPager(pager(['c1']))).toBe(true);
        expect(isOnlyChildPager(pager(['c1', 'c2']))).toBe(false);
    });
});

describe('mayHaveChildren', () => {
    it('is false for loaded children without any', () => {
        expect(mayHaveChildren(node(), pager([]))).toBe(false);
    });

    it('is false for an empty children hint', () => {
        expect(mayHaveChildren(node([]), pager([], {loaded: false}))).toBe(
            false
        );
        expect(mayHaveChildren(node([]), undefined)).toBe(false);
    });

    it('is true for a children hint with ids', () => {
        expect(
            mayHaveChildren(node([{id: 'c2'}]), pager(['c2'], {loaded: false}))
        ).toBe(true);
    });

    it('is true when nothing is known', () => {
        expect(mayHaveChildren(node(), undefined)).toBe(true);
    });
});

describe('useAutoExpand', () => {
    it('does not expand while the condition is false', () => {
        const expand = vi.fn();
        renderHook(() => useAutoExpand(false, expand));

        expect(expand).not.toHaveBeenCalled();
    });

    it('expands once when the condition is true', () => {
        const expand = vi.fn();
        const {rerender} = renderHook(
            ({should}) => useAutoExpand(should, expand),
            {initialProps: {should: true}}
        );
        rerender({should: true});

        expect(expand).toHaveBeenCalledTimes(1);
    });

    it('expands once when the condition becomes true later', () => {
        const expand = vi.fn();
        const {rerender} = renderHook(
            ({should}) => useAutoExpand(should, expand),
            {initialProps: {should: false}}
        );
        expect(expand).not.toHaveBeenCalled();

        rerender({should: true});
        rerender({should: false});
        rerender({should: true});

        expect(expand).toHaveBeenCalledTimes(1);
    });
});
