'use client';

import {
    createContext,
    useContext,
    useState,
    type PropsWithChildren,
    type ReactNode,
} from 'react';
import {
    DndContext,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragMoveEvent,
} from '@dnd-kit/core';
import {getEventCoordinates} from '@dnd-kit/utilities';
import type {AttributeEntity} from '@/types/api';
import {mostSpecificPointerWithin} from '@/features/dnd/collision';
import {SortableOverlay, sortableMeasuring} from '@alchemy/next-ui/ui/sortable';

/** Where a node receives the dragged entity: as a sibling or as its last child */
export type DropZone = 'before' | 'inside' | 'after';

/**
 * A move resulting from a drop: the new parent (null: the top level) and the
 * position among the siblings, counted without the moved entity.
 */
export type HierarchyDrop = {
    entity: AttributeEntity;
    parentId: string | null;
    position: number;
    /** The parent changes: the assets holding the branch are recomputed */
    reparent: boolean;
};

export type SourceData = {
    entity: AttributeEntity;
    /** Position among the siblings */
    index: number;
};

export type TargetData =
    | {
          kind: 'node';
          entity: AttributeEntity;
          index: number;
          /** Rendered ancestors: a branch cannot go under itself */
          ancestorIds: string[];
      }
    | {kind: 'top'};

type DropTarget = {id: string; zone: DropZone; drop: HierarchyDrop};

const topId = 'top';
const nodeId = (id: string) => `node:${id}`;

const DropTargetContext = createContext<DropTarget | undefined>(undefined);

/**
 * Drag & drop of the hierarchy tree: a node is dragged onto another one
 * (before, inside or after it) or onto the free space of the tree, which puts
 * it at the end of the top level.
 */
export function HierarchyDndProvider({
    onDrop,
    renderOverlay,
    children,
}: PropsWithChildren<{
    onDrop: (drop: HierarchyDrop) => void;
    renderOverlay: (entity: AttributeEntity) => ReactNode;
}>) {
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}})
    );
    const [active, setActive] = useState<AttributeEntity>();
    const [target, setTarget] = useState<DropTarget>();

    const track = (event: DragMoveEvent) => {
        const next = locate(event);
        // Called on every pointer move: re-render the tree only on a change
        setTarget(prev =>
            prev?.id === next?.id && prev?.zone === next?.zone ? prev : next
        );
    };

    const end = (event: DragEndEvent) => {
        const drop = locate(event)?.drop;
        setActive(undefined);
        setTarget(undefined);
        if (drop) {
            onDrop(drop);
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={mostSpecificPointerWithin}
            measuring={sortableMeasuring}
            onDragStart={({active}) =>
                setActive((active.data.current as SourceData).entity)
            }
            onDragMove={track}
            onDragEnd={end}
            onDragCancel={() => {
                setActive(undefined);
                setTarget(undefined);
            }}
        >
            <DropTargetContext.Provider value={target}>
                {children}
            </DropTargetContext.Provider>
            <SortableOverlay>
                {() => (active ? renderOverlay(active) : null)}
            </SortableOverlay>
        </DndContext>
    );
}

function locate({
    active,
    over,
    activatorEvent,
    delta,
}: DragMoveEvent): DropTarget | undefined {
    const source = active.data.current as SourceData | undefined;
    const data = over?.data.current as TargetData | undefined;
    if (!source || !over || !data) {
        return undefined;
    }

    let zone: DropZone = 'inside';
    if (data.kind === 'node') {
        const start = getEventCoordinates(activatorEvent);
        if (start) {
            const ratio =
                (start.y + delta.y - over.rect.top) / over.rect.height;
            zone = ratio < 0.25 ? 'before' : ratio > 0.75 ? 'after' : 'inside';
        }
    }
    const drop = resolveDrop(source, data, zone);

    return drop ? {id: String(over.id), zone, drop} : undefined;
}

export function resolveDrop(
    {entity, index}: SourceData,
    target: TargetData,
    zone: DropZone
): HierarchyDrop | undefined {
    const currentParentId = entity.parentId ?? null;

    if (target.kind === 'top') {
        return currentParentId !== null
            ? {
                  entity,
                  parentId: null,
                  position: Number.MAX_SAFE_INTEGER,
                  reparent: true,
              }
            : undefined;
    }
    if (
        target.entity.id === entity.id ||
        target.ancestorIds.includes(entity.id)
    ) {
        return undefined;
    }

    if (zone === 'inside') {
        return currentParentId === target.entity.id
            ? undefined
            : {
                  entity,
                  parentId: target.entity.id,
                  position: Number.MAX_SAFE_INTEGER,
                  reparent: true,
              };
    }

    const parentId = target.entity.parentId ?? null;
    let position = target.index + (zone === 'after' ? 1 : 0);
    const sameParent = currentParentId === parentId;
    if (sameParent) {
        // The API counts the siblings without the moved entity
        if (index < position) {
            position--;
        }
        if (position === index) {
            return undefined;
        }
    }

    return {entity, parentId, position, reparent: !sameParent};
}

/**
 * A row of the tree: dragged by its handle, and the drop zone under the
 * pointer when it is the target.
 */
export function useHierarchyNode({
    entity,
    index,
    ancestorIds,
}: {
    entity: AttributeEntity;
    index: number;
    ancestorIds: string[];
}) {
    const id = nodeId(entity.id);
    const droppable = useDroppable({
        id,
        data: {
            kind: 'node',
            entity,
            index,
            ancestorIds,
        } satisfies TargetData,
    });
    const draggable = useDraggable({
        id,
        data: {entity, index} satisfies SourceData,
    });
    const target = useContext(DropTargetContext);

    return {
        setNodeRef: (node: HTMLElement | null) => {
            droppable.setNodeRef(node);
            draggable.setNodeRef(node);
        },
        handle: {...draggable.attributes, ...draggable.listeners},
        isDragging: draggable.isDragging,
        zone: target?.id === id ? target.zone : undefined,
    };
}

/** The free space of the tree, which moves the dropped branch to the top level */
export function useTopLevelDrop() {
    const {setNodeRef} = useDroppable({
        id: topId,
        data: {kind: 'top'} satisfies TargetData,
    });
    const target = useContext(DropTargetContext);

    return {setNodeRef, isOver: target?.id === topId};
}

/** Marks where the dragged entity will land on a row */
export const dropZoneClass: Record<DropZone, string> = {
    before: 'shadow-[inset_0_2px_0_0_var(--primary)]',
    inside: 'bg-primary/10 ring-1 ring-primary',
    after: 'shadow-[inset_0_-2px_0_0_var(--primary)]',
};
