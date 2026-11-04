import type {Modifier} from '@dnd-kit/core';
import {getEventCoordinates} from '@dnd-kit/utilities';

const offset = 14;

/**
 * Keeps the top-left corner of the drag overlay next to the pointer, wherever
 * the drag started on the source element. The overlay no longer depends on
 * the source rect: a virtualized row unmounting mid-drag cannot move it.
 */
export const snapToCursor: Modifier = ({
    activatorEvent,
    draggingNodeRect,
    transform,
}) => {
    if (!draggingNodeRect || !activatorEvent) {
        return transform;
    }
    const coordinates = getEventCoordinates(activatorEvent);
    if (!coordinates) {
        return transform;
    }

    return {
        ...transform,
        x: transform.x + (coordinates.x - draggingNodeRect.left) + offset,
        y: transform.y + (coordinates.y - draggingNodeRect.top) + offset,
    };
};
