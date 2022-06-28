import {Button, InputLabel} from "@mui/material";
import {FieldArrayWithId, useFieldArray} from "react-hook-form";
import AddIcon from "@mui/icons-material/Add";
import {useState} from "react";
import {useTranslation} from "react-i18next";
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    DragOverlay,
    DragStartEvent,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {SortableContext, useSortable, verticalListSortingStrategy,} from '@dnd-kit/sortable';
import {CSS} from "@dnd-kit/utilities";
import {CollectionItem, CollectionItemProps, CollectionWidgetProps} from "./CollectionWidget";
import {SortableItem} from "../Ui/Sortable/SortableList";

function SortableCollectionItem<TFieldValues>({id, ...props}: { id: string } & CollectionItemProps<TFieldValues>) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
    } = useSortable({id});

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        touchAction: 'manipulation',
    };

    return <div
        ref={setNodeRef}
        style={style}
        {...attributes}>
        <CollectionItem
            {...props}
            dragListeners={listeners}
            sortable={true}
        />
    </div>
}

function getNextPosition<TFieldValues>(fields: (FieldArrayWithId<TFieldValues> & SortableItem)[]): number {
    if (fields.length === 0) {
        return 0;
    }

    return Math.max(...fields.map(f => f.position)) + 1
}

export default function SortableCollectionWidget<TFieldValues>({
                                                                   path,
                                                                   emptyItem,
                                                                   renderForm,
                                                                   control,
                                                                   register,
                                                                   label,
                                                                   removeLabel,
                                                                   addLabel,
                                                               }: CollectionWidgetProps<TFieldValues>) {
    const {fields: _fields, remove, append, swap, update} = useFieldArray<TFieldValues>({
        control,
        name: path as unknown as any,
    });
    const fields = _fields as unknown as (FieldArrayWithId<TFieldValues> & SortableItem)[];

    const [activeId, setActiveId] = useState<string | null>(null);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor),
    );

    const {t} = useTranslation();

    const appendItem = () => {
        append({
            ...emptyItem,
            position: getNextPosition(fields),
        } as any);
    };

    function handleDragEnd(event: DragEndEvent) {
        const {active, over} = event;

        if (over && active.id !== over.id) {
            const indexA = fields.findIndex(f => f.id === active.id);
            const indexB = fields.findIndex(f => f.id === over.id);

            const currentFields = control._getWatch(path);

            update(indexA, {
                ...(currentFields[indexA] as any),
                position: currentFields[indexB].position,
            });
            update(indexB, {
                ...(currentFields[indexB] as any),
                position: currentFields[indexA].position,
            });
            swap(indexA, indexB);
        }
        setActiveId(null);
    }

    function handleDragStart({active}: DragStartEvent) {
        setActiveId(active.id as string);
    }

    const rLabel = removeLabel || t('form.collection.remove', 'Remove');
    const activeFieldIndex = null !== activeId ? fields.findIndex(f => f.id === activeId) : null;
    const activeField = null !== activeFieldIndex ? fields[activeFieldIndex] : null;

    return <div>
        <InputLabel>{label}</InputLabel>
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
            onDragStart={handleDragStart}
        >
            <SortableContext
                items={fields}
                strategy={verticalListSortingStrategy}
            >
                {fields.map((field, index) => {
                    return <SortableCollectionItem
                        key={field.id}
                        id={field.id}
                        renderForm={renderForm}
                        remove={remove}
                        removeLabel={rLabel}
                        register={register}
                        path={path}
                        index={index}
                    />
                })}
            </SortableContext>
            <DragOverlay>
                {activeField && <div style={{
                    backgroundColor: '#FFF',
                }}>
                    <CollectionItem
                        renderForm={renderForm}
                        remove={remove}
                        removeLabel={rLabel}
                        register={register}
                        path={path}
                        sortable={true}
                        index={activeFieldIndex!}
                    />
                </div>}
            </DragOverlay>
        </DndContext>
        <Button
            onClick={appendItem}
            startIcon={<AddIcon/>}>
            {addLabel || t('form.collection.add', 'Add')}
        </Button>
    </div>
}
