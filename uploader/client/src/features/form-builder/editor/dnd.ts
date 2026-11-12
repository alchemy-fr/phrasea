import {
    closestCenter,
    type Collision,
    type CollisionDetection,
    type UniqueIdentifier,
} from '@dnd-kit/core';

/** Droppable id of the form, where palette items are dropped */
export const CANVAS_ZONE = 'canvas';

const PALETTE_PREFIX = 'palette:';

export const paletteDragId = (id: string) => `${PALETTE_PREFIX}${id}`;

export function paletteIdOf(id: UniqueIdentifier): string | undefined {
    const s = String(id);

    return s.startsWith(PALETTE_PREFIX)
        ? s.slice(PALETTE_PREFIX.length)
        : undefined;
}

/** Where a palette item dragged over the form would land */
export type CanvasDrop = {index: number};

/**
 * A field of the form being moved: the closest one, as for any sortable list.
 *
 * A palette item: the form when the pointer is over it, with the place
 * between the fields the pointer is closest to (see {@link canvasDropOf}).
 * Read from the elements as they are now rather than from the rects dnd-kit
 * measured when the drag started: the columns are sticky, each scrolled at
 * its own pace with the page (see `useSyncedColumns`), while dnd-kit moves
 * them by the scroll of the page.
 */
export const canvasCollision: CollisionDetection = args => {
    const fields = args.droppableContainers.filter(c => c.id !== CANVAS_ZONE);
    if (!paletteIdOf(args.active.id)) {
        return closestCenter({...args, droppableContainers: fields});
    }
    const canvas = args.droppableContainers.find(c => c.id === CANVAS_ZONE)
        ?.node.current;
    const pointer = args.pointerCoordinates;
    if (!canvas || !pointer) {
        return [];
    }
    const box = canvas.getBoundingClientRect();
    if (
        pointer.x < box.left ||
        pointer.x > box.right ||
        pointer.y < box.top ||
        pointer.y > box.bottom
    ) {
        return [];
    }
    // The fields above the pointer
    const index = fields.filter(c => {
        const rect = c.node.current?.getBoundingClientRect();

        return rect && rect.top + rect.height / 2 < pointer.y;
    }).length;
    const drop: CanvasDrop = {index};

    return [{id: CANVAS_ZONE, data: drop}];
};

export function canvasDropOf(
    collisions: Collision[] | null
): CanvasDrop | undefined {
    const collision = collisions?.find(c => c.id === CANVAS_ZONE);

    return collision?.data as CanvasDrop | undefined;
}
