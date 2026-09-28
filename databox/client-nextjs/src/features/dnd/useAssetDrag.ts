'use client';

import {useDraggable} from '@dnd-kit/core';
import type {Asset} from '@/types/api';
import {useSelectionActions} from '@/features/assets/list/SelectionProvider';
import {useDragScope} from './DragScope';
import {useDndEnabled} from './DragContext';
import {type AssetDragSource, dragId} from './types';

/**
 * Makes an asset card draggable. Dragging a selected card takes the whole
 * selection along (resolved when the drag starts, see `AppDndProvider`).
 */
export function useAssetDrag(asset: Asset) {
    const {scope, basketId} = useDragScope();
    const {getSelection} = useSelectionActions();
    const enabled = useDndEnabled();
    const {setNodeRef, listeners, isDragging} = useDraggable({
        id: dragId.asset(scope, asset.id),
        data: {
            kind: 'asset-source',
            asset,
            scope,
            basketId,
            getSelection,
        } satisfies AssetDragSource,
        disabled: !enabled || !!asset.deleted,
    });

    return {setNodeRef, listeners, isDragging};
}
