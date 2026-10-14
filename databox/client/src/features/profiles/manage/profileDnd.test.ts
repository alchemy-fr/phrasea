import {describe, expect, it} from 'vitest';
import {paletteDragId, paletteKeyOf, zoneCollision} from './profileDnd';

function rect(left: number, top: number, width: number, height: number) {
    return {
        left,
        top,
        width,
        height,
        right: left + width,
        bottom: top + height,
    };
}

type Droppable = {id: string; rect: ReturnType<typeof rect>; data: object};

function collide(
    droppables: Droppable[],
    pointer: {x: number; y: number} | null
) {
    const at = pointer ?? {x: 0, y: 0};

    return zoneCollision({
        active: {id: 'dragged'},
        collisionRect: rect(at.x - 5, at.y - 5, 10, 10),
        droppableRects: new Map(droppables.map(d => [d.id, d.rect])),
        droppableContainers: droppables.map(d => ({
            id: d.id,
            data: {current: d.data},
        })),
        pointerCoordinates: pointer,
    } as any).map(c => c.id);
}

// A list holding two rows 4 px apart, a card cell inside a larger
// card, and the palette on the left
const list = {id: 'list', rect: rect(100, 0, 300, 200), data: {kind: 'zone'}};
const row1 = {
    id: 'row1',
    rect: rect(100, 0, 300, 30),
    data: {kind: 'item', zone: 'list'},
};
const row2 = {
    id: 'row2',
    rect: rect(100, 34, 300, 30),
    data: {kind: 'item', zone: 'list'},
};
const card = {id: 'card', rect: rect(500, 0, 200, 200), data: {kind: 'zone'}};
const cell = {id: 'cell', rect: rect(520, 20, 50, 50), data: {kind: 'zone'}};
const palette = {
    id: 'palette',
    rect: rect(0, 0, 90, 200),
    data: {kind: 'zone'},
};
const all = [list, row1, row2, card, cell, palette];

describe('zoneCollision', () => {
    it('resolves the gap between two items to the closest one', () => {
        expect(collide(all, {x: 200, y: 31})[0]).toBe('row1');
        expect(collide(all, {x: 200, y: 33})[0]).toBe('row2');
    });

    it('resolves the empty space of a zone to its closest item', () => {
        expect(collide(all, {x: 200, y: 150})[0]).toBe('row2');
    });

    it('gives the zone itself when it holds no item', () => {
        expect(collide(all, {x: 20, y: 100})).toEqual(['palette']);
    });

    it('prefers the smallest zone under the pointer', () => {
        expect(collide(all, {x: 540, y: 40})).toEqual(['cell']);
        expect(collide(all, {x: 650, y: 150})).toEqual(['card']);
    });

    it('gives nothing outside of every zone', () => {
        expect(collide(all, {x: 450, y: 100})).toEqual([]);
    });
});

describe('palette drag ids', () => {
    it('round-trips the entry key', () => {
        expect(paletteKeyOf(paletteDragId('d:123'))).toBe('d:123');
        expect(paletteKeyOf('item-id')).toBeUndefined();
    });
});
