import {describe, expect, it} from 'vitest';
import type {Asset} from '@/types/api';
import {computeSelection} from './SelectionProvider';

const assets = ['a', 'b', 'c', 'd', 'e', 'f'].map(id => ({id}) as Asset);
const pages = [assets.slice(0, 3), assets.slice(3)];
const byId = (id: string) => assets.find(a => a.id === id)!;
const ids = (list: Asset[]) => list.map(a => a.id);
const select = (current: string[], id: string, e: object, anchor?: string) =>
    ids(computeSelection(current.map(byId), byId(id), pages, e, anchor));

describe('computeSelection', () => {
    it('selects the clicked item alone', () => {
        expect(select(['a', 'b'], 'c', {})).toEqual(['c']);
    });

    it('toggles the item with Ctrl', () => {
        expect(select(['a'], 'c', {ctrlKey: true})).toEqual(['a', 'c']);
        expect(select(['a', 'c'], 'c', {metaKey: true})).toEqual(['a']);
    });

    it('selects from the anchor with Shift, across pages', () => {
        expect(select(['b'], 'e', {shiftKey: true}, 'b')).toEqual([
            'b',
            'c',
            'd',
            'e',
        ]);
        expect(
            select(['b', 'c', 'd', 'e'], 'a', {shiftKey: true}, 'b')
        ).toEqual(['a', 'b']);
    });

    it('adds the range to the selection with Shift+Ctrl', () => {
        expect(
            select(['a', 'd'], 'f', {shiftKey: true, ctrlKey: true}, 'd')
        ).toEqual(['a', 'd', 'e', 'f']);
    });

    it('falls back on the last selected item without anchor', () => {
        expect(select(['e'], 'c', {shiftKey: true})).toEqual(['c', 'd', 'e']);
        expect(select([], 'c', {shiftKey: true})).toEqual(['c']);
    });
});
