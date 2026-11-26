import {describe, expect, it, vi} from 'vitest';
import {renderHook} from '@testing-library/react';
import {arrayMove} from '@dnd-kit/sortable';
import type {Asset} from '@/types/api';
import {rewriteItems, useStoryPagesUntil} from './StoryCarousel';

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

describe('useStoryPagesUntil', () => {
    type Story = Parameters<typeof useStoryPagesUntil>[0];

    function story(ids: string[], more: Partial<Story> = {}): Story {
        return {
            items: ids.map(asset),
            hasNextPage: true,
            isFetchingNextPage: false,
            fetchNextPage: vi.fn(),
            ...more,
        } as Story;
    }

    it('loads the next pages until the item is found', () => {
        const fetchNextPage = vi.fn();
        const {rerender} = renderHook(
            ({s}: {s: Story}) => useStoryPagesUntil(s, 'a45'),
            {initialProps: {s: story(['a1', 'a2'], {fetchNextPage})}}
        );
        expect(fetchNextPage).toHaveBeenCalledTimes(1);

        rerender({
            s: story(['a1', 'a2'], {fetchNextPage, isFetchingNextPage: true}),
        });
        expect(fetchNextPage).toHaveBeenCalledTimes(1);

        rerender({s: story(['a1', 'a2', 'a3'], {fetchNextPage})});
        expect(fetchNextPage).toHaveBeenCalledTimes(2);

        rerender({s: story(['a1', 'a2', 'a3', 'a45'], {fetchNextPage})});
        expect(fetchNextPage).toHaveBeenCalledTimes(2);
    });

    it('stops at the last page, or without an item', () => {
        const last = story(['a1'], {hasNextPage: false});
        renderHook(() => useStoryPagesUntil(last, 'a45'));
        expect(last.fetchNextPage).not.toHaveBeenCalled();

        const cover = story(['a1']);
        renderHook(() => useStoryPagesUntil(cover, undefined));
        expect(cover.fetchNextPage).not.toHaveBeenCalled();
    });
});
