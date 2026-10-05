'use client';

import {useDraggable} from '@dnd-kit/core';
import type {CollectionNode} from '@/features/collections/collectionStore';
import {useDndEnabled} from './DragContext';
import {type CollectionDragSource, dragId} from './types';

/** Makes a collection row draggable, to move the collection in the tree. */
export function useCollectionDrag(collection: CollectionNode) {
    const enabled = useDndEnabled();
    const {setNodeRef, listeners, isDragging} = useDraggable({
        id: dragId.collection(collection.id),
        data: {
            kind: 'collection-source',
            collection,
        } satisfies CollectionDragSource,
        disabled:
            !enabled || !collection.capabilities.edit || !!collection.deleted,
    });

    return {setNodeRef, listeners, isDragging};
}
