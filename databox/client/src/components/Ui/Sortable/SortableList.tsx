import React, {FunctionComponent, useState} from 'react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    DragOverlay,
    DragStartEvent,
    MouseSensor,
    TouchSensor,
    useSensor,
    useSensors
} from "@dnd-kit/core";
import {arrayMove, SortableContext, verticalListSortingStrategy} from "@dnd-kit/sortable";
import SortableNode from "./SortableNode";

export type SortableItem = {
    id: string;
    position: number;
};

export type SortableItemProps<D extends SortableItem> = {
    data: D;
};

export type OrderChangeHandler<D extends SortableItem> = (orderedData: D[]) => void;

type Props<D extends SortableItem, ItemProps extends {}> = {
    onOrderChange: OrderChangeHandler<D>;
    list: D[];
    itemProps: ItemProps;
    itemComponent: FunctionComponent<{
        data: D;
        itemProps: ItemProps;
    }>;
};


export function getNextPosition(fields: SortableItem[]): number {
    if (fields.length === 0) {
        return 0;
    }

    return Math.max(...fields.map(f => f.position)) + 1
}

export default function SortableList<D extends SortableItem, ItemProps extends {}>({
                                                                                       list,
                                                                                       onOrderChange,
                                                                                       itemComponent,
                                                                                       itemProps,
                                                                                   }: Props<D, ItemProps>) {
    const [activeId, setActiveId] = useState<string | null>(null);
    const activeIndex = null !== activeId ? list.findIndex(f => f.id === activeId) : null;
    const activeItem = null !== activeIndex ? list[activeIndex] : null;

    const sensors = useSensors(
        useSensor(MouseSensor, {
            activationConstraint: {
                distance: 20
            }
        }),
        useSensor(TouchSensor),
    );

    function handleDragEnd(event: DragEndEvent) {
        const {active, over} = event;

        setActiveId(null);
        if (over && active.id !== over.id) {
            const a = list.findIndex(f => f.id === active.id);
            const b = list.findIndex(f => f.id === over.id);

            let p = 0;
            const newList = arrayMove(list, a, b).map(i => ({
                ...i,
                position: p++,
            }));

            onOrderChange(newList);
        }
    }

    function handleDragStart({active}: DragStartEvent) {
        setActiveId(active.id as string);
    }

    return <>
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
            onDragStart={handleDragStart}
        >
            <SortableContext
                items={list}
                strategy={verticalListSortingStrategy}
            >
                {list.map(i => <SortableNode
                    id={i.id}
                    key={i.id}
                >
                    {React.createElement(itemComponent, {
                        itemProps,
                        data: i,
                    })}
                </SortableNode>)}
            </SortableContext>
            <DragOverlay>
                {activeItem && <div style={{
                    backgroundColor: '#FFF',
                }}>
                    {React.createElement(itemComponent, {
                        itemProps,
                        data: activeItem!,
                    })}
                </div>}
            </DragOverlay>
        </DndContext>
    </>
}
