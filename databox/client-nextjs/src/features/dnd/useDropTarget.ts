'use client';

import {useDroppable} from '@dnd-kit/core';
import {cn} from '@/lib/utils/cn';
import {useDragOver, useDragPayload} from './DragContext';
import {type DropTarget, type DropVerdict, dropId} from './types';

/**
 * Registers a sidebar row (collection, workspace, basket, story…) as a drop
 * target and tells how to highlight it.
 */
export function useDropTarget(target: DropTarget) {
    const payload = useDragPayload();
    const over = useDragOver();
    const {setNodeRef, isOver} = useDroppable({
        id: dropId(target),
        data: target,
    });
    const verdict: DropVerdict | null = isOver && payload ? over.verdict : null;

    return {
        setNodeRef,
        isOver: isOver && !!payload,
        /** A drag is in progress (whatever its target) */
        active: !!payload,
        verdict,
        dropClass: cn(
            verdict?.ok && 'bg-primary/20 ring-1 ring-primary ring-inset',
            verdict && !verdict.ok && 'bg-destructive/10'
        ),
    };
}
