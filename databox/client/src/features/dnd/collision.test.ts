import {describe, expect, it, vi} from 'vitest';

const pointerWithin = vi.hoisted(() => vi.fn());
vi.mock('@dnd-kit/core', () => ({pointerWithin}));

const {mostSpecificPointerWithin} = await import('./collision');

describe('mostSpecificPointerWithin', () => {
    it('puts the smallest droppable first', () => {
        pointerWithin.mockReturnValue([
            {id: 'panel', data: {}},
            {id: 'row', data: {}},
        ]);
        const droppableRects = new Map([
            ['panel', {width: 300, height: 800}],
            ['row', {width: 300, height: 28}],
        ]);
        const result = mostSpecificPointerWithin({droppableRects} as any);
        expect(result.map(c => c.id)).toEqual(['row', 'panel']);
    });
});
