'use client';

import {createContext, useContext} from 'react';
import {
    type DragModifiers,
    type DragPayload,
    type DropTarget,
    type DropVerdict,
    noModifiers,
} from './types';

/** The payload being dragged: changes at drag start and end only */
export const DragPayloadContext = createContext<DragPayload | null>(null);

export type DragOverState = {
    /** The droppable under the pointer */
    target: DropTarget | null;
    /** Its verdict, null over a neutral target (tab, panel) or nothing */
    verdict: DropVerdict | null;
    modifiers: DragModifiers;
};

export const emptyDragOver: DragOverState = {
    target: null,
    verdict: null,
    modifiers: noModifiers,
};

/** Where the payload is, and whether it can be dropped there */
export const DragOverContext = createContext<DragOverState>(emptyDragOver);

/** Dragging is enabled (the user is signed in) */
export const DndEnabledContext = createContext(false);

export function useDragPayload(): DragPayload | null {
    return useContext(DragPayloadContext);
}

export function useDragOver(): DragOverState {
    return useContext(DragOverContext);
}

export function useDndEnabled(): boolean {
    return useContext(DndEnabledContext);
}
