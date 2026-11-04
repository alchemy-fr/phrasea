import {describe, expect, it} from 'vitest';
import {arrayMove} from '@dnd-kit/sortable';
import type {Asset} from '@/types/api';
import {rewriteItems} from './StoryCarousel';

const asset = (id: string) => ({id}) as Asset;

function pages(...ids: string[][]) {
    const total = ids.flat().length + 1;

    return {
        pageParams: ids.map((_, i) => (i === 0 ? undefined : `p${i}`)),
        pages: ids.map(p => ({items: p.map(asset), total, facets: {}})),
    };
}

const idsOf = (data: ReturnType<typeof rewriteItems>) =>
    data!.pages.map(p => p.items.map(a => a.id));

describe('rewriteItems', () => {
    it('reorders across pages, keeping their size', () => {
        const data = rewriteItems(
            pages(['a', 'b'], ['c', 'd']),
            l => arrayMove(l, 3, 0),
            0
        );

        expect(idsOf(data)).toEqual([
            ['d', 'a'],
            ['b', 'c'],
        ]);
        expect(data!.pages[0].total).toBe(5);
    });

    it('shrinks the last page and the total on removal', () => {
        const data = rewriteItems(
            pages(['a', 'b'], ['c', 'd']),
            l => l.filter(a => a.id !== 'a'),
            1
        );

        expect(idsOf(data)).toEqual([['b', 'c'], ['d']]);
        expect(data!.pages.map(p => p.total)).toEqual([4, 4]);
    });

    it('keeps the total when an item leaves the loaded pages', () => {
        const data = rewriteItems(
            pages(['a', 'b']),
            l => l.filter(a => a.id !== 'a'),
            0
        );

        expect(idsOf(data)).toEqual([['b']]);
        expect(data!.pages[0].total).toBe(3);
    });
});
