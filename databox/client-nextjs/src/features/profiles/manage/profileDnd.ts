import {
    closestCenter,
    pointerWithin,
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
