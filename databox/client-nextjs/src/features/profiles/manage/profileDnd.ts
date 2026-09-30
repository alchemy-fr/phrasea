import {useEffect, useRef} from 'react';
import {
    closestCenter,
    pointerWithin,
    type Active,
    type CollisionDetection,
    type UniqueIdentifier,
} from '@dnd-kit/core';

/** Droppable id of the attribute palette: dropping an item there removes it */
export const PALETTE_ZONE = 'palette';

const PALETTE_PREFIX = 'palette:';

export const paletteDragId = (key: string) => `${PALETTE_PREFIX}${key}`;

export function paletteKeyOf(id: UniqueIdentifier): string | undefined {
    const s = String(id);

    return s.startsWith(PALETTE_PREFIX)
        ? s.slice(PALETTE_PREFIX.length)
        : undefined;
}

/** `data` of a droppable zone (a list, a card cell, the palette) */
export type ZoneData = {kind: 'zone'};
/** `data` of an item sortable within a zone */
export type ZoneItemData = {kind: 'item'; zone: string};

/**
 * The zone under the pointer (the smallest one: a card cell rather than the
 * card) then, within it, the item closest to the pointer — the gaps between
 * the items resolve to their neighbour instead of flickering to the zone
 * itself. Without a pointer (keyboard), the closest droppable.
 */
export const zoneCollision: CollisionDetection = args => {
    if (!args.pointerCoordinates) {
        return closestCenter(args);
    }
    const area = (id: UniqueIdentifier) => {
        const rect = args.droppableRects.get(id);

        return rect ? rect.width * rect.height : Number.POSITIVE_INFINITY;
    };
    const zones = pointerWithin({
        ...args,
        droppableContainers: args.droppableContainers.filter(
            c => c.data.current?.kind === 'zone'
        ),
    }).sort((a, b) => area(a.id) - area(b.id));
    const zone = zones[0];
    if (!zone) {
        return [];
    }
    const items = args.droppableContainers.filter(
        c => c.data.current?.kind === 'item' && c.data.current?.zone === zone.id
    );
    if (items.length === 0) {
        return [zone];
    }

    return closestCenter({...args, droppableContainers: items});
};

/**
 * Where the drag is, to place a drop before or after the item hovered: the
 * pointer, followed on the window. The `delta` of the drag events cannot
 * tell: dnd-kit adjusts it with the scroll of the containers under the
 * pointer, and moving from the scrolled palette to a list drops the palette
 * scroll from it (the pointer then seems higher than it is). A keyboard drag
 * has no pointer: the center of the dragged rect.
 */
export function useDragPosition() {
    const pointer = useRef<{x: number; y: number} | null>(null);

    useEffect(() => {
        const onMove = (e: PointerEvent) => {
            pointer.current = {x: e.clientX, y: e.clientY};
        };
        window.addEventListener('pointermove', onMove, {
            capture: true,
            passive: true,
        });

        return () =>
            window.removeEventListener('pointermove', onMove, {capture: true});
    }, []);

    return (active: Active, activatorEvent: Event | null) => {
        const rect = active.rect.current.translated;
        if (
            (activatorEvent &&
                typeof KeyboardEvent !== 'undefined' &&
                activatorEvent instanceof KeyboardEvent) ||
            !pointer.current
        ) {
            return rect
                ? {
                      x: rect.left + rect.width / 2,
                      y: rect.top + rect.height / 2,
                  }
                : {x: 0, y: 0};
        }

        return pointer.current;
    };
}
