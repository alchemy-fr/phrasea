import {pointerWithin, type CollisionDetection} from '@dnd-kit/core';

/**
 * `pointerWithin`, the smallest droppable first: a sidebar row wins over the
 * panel that contains it (the panel is droppable only to keep auto-scroll
 * going between rows).
 */
export const mostSpecificPointerWithin: CollisionDetection = args => {
    const collisions = pointerWithin(args);
    if (collisions.length <= 1) {
        return collisions;
    }
    const area = (id: (typeof collisions)[number]['id']) => {
        const rect = args.droppableRects.get(id);

        return rect ? rect.width * rect.height : Number.POSITIVE_INFINITY;
    };

    return [...collisions].sort((a, b) => area(a.id) - area(b.id));
};
