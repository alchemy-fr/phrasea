import {describe, expect, it} from 'vitest';
import {mergeOrder, moveItem, resolveOrder} from './order';

describe('resolveOrder', () => {
    it('keeps the default order when nothing is saved', () => {
        expect(resolveOrder(undefined, ['a', 'b', 'c'])).toEqual([
            'a',
            'b',
            'c',
        ]);
    });

    it('drops the saved items not available', () => {
        expect(resolveOrder(['c', 'x', 'a', 'c'], ['a', 'b', 'c'])).toEqual([
            'c',
            'a',
            'b',
        ]);
    });
});

describe('mergeOrder', () => {
    it('saves the new order when nothing was saved', () => {
        expect(mergeOrder(undefined, ['b', 'a'])).toEqual(['b', 'a']);
    });

    it('keeps the slot of the saved items not displayed', () => {
        expect(mergeOrder(['a', 'x', 'b'], ['c', 'a', 'b'])).toEqual([
            'c',
            'x',
            'a',
            'b',
        ]);
        expect(
            resolveOrder(mergeOrder(['a', 'x', 'b'], ['b', 'a']), [
                'a',
                'b',
                'x',
            ])
        ).toEqual(['b', 'x', 'a']);
    });
});

describe('moveItem', () => {
    it('moves an item down and up', () => {
        expect(moveItem(['a', 'b', 'c'], 0, 2)).toEqual(['b', 'c', 'a']);
        expect(moveItem(['a', 'b', 'c'], 2, 0)).toEqual(['c', 'a', 'b']);
    });
});
