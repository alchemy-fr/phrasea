import {Button, InputLabel} from '@mui/material';
import {FieldArrayWithId, FieldValues, useFieldArray} from 'react-hook-form';
import AddIcon from '@mui/icons-material/Add';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
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
import {SortableContext, verticalListSortingStrategy,} from '@dnd-kit/sortable';
import {CollectionWidgetProps} from './CollectionWidget';
import SortableCollectionItem from "./SortableCollectionItem";
import {CollectionItem} from "./CollectionItem";

type Sortable = {};

export default function SortableCollectionWidget<
    TFieldValues extends FieldValues,
>({
    path,
    emptyItem,
    renderForm,
    max,
    errors,
    control,
    register,
    label,
    removeLabel,
    addLabel,
}: CollectionWidgetProps<TFieldValues>) {
    const {
        fields: _fields,
        remove,
        append,
        move,
        update,
    } = useFieldArray<TFieldValues>({
        control,
        name: path as unknown as any,
    });
    const fields = _fields as unknown as (FieldArrayWithId<TFieldValues> &
        Sortable)[];

    const [activeId, setActiveId] = useState<string | null>(null);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(TouchSensor),
    );

    const {t} = useTranslation();

    const appendItem = () => {
        append(typeof emptyItem === 'string' ? emptyItem : {
            ...emptyItem,
        } as any);
    };

    function handleDragEnd(event: DragEndEvent) {
        const {active, over} = event;

        if (over && active.id !== over.id) {
            const indexA = fields.findIndex(f => f.id === active.id);
            const indexB = fields.findIndex(f => f.id === over.id);

            move(indexA, indexB);
        }
        setActiveId(null);
    }

    function handleDragStart({active}: DragStartEvent) {
        setActiveId(active.id as string);
    }

    const rLabel = removeLabel || t('lib.form.collection.remove', 'Remove');
    const activeFieldIndex =
        null !== activeId ? fields.findIndex(f => f.id === activeId) : null;
    const activeField =
        null !== activeFieldIndex ? fields[activeFieldIndex] : null;

    return (
        <div>
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
                        return (
                            <SortableCollectionItem
                                key={field.id}
                                id={field.id}
                                renderForm={renderForm}
                                remove={remove}
                                errors={errors}
                                removeLabel={rLabel}
                                register={register}
                                path={path}
                                index={index}
                                data={field}
                                update={update}
                            />
                        );
                    })}
                </SortableContext>
                <DragOverlay>
                    {activeField && (
                        <div
                            style={{
                                backgroundColor: '#FFF',
                            }}
                        >
                            <CollectionItem
                                renderForm={renderForm}
                                remove={remove}
                                removeLabel={rLabel}
                                register={register}
                                path={path}
                                errors={errors}
                                sortable={true}
                                index={activeFieldIndex!}
                                data={undefined}
                                update={update}
                            />
                        </div>
                    )}
                </DragOverlay>
            </DndContext>

            <Button
                onClick={appendItem}
                disabled={Boolean(max) && fields.length >= max!}
                startIcon={<AddIcon/>}
            >
                {addLabel || t('lib.form.collection.add', 'Add')}
            </Button>
        </div>
    );
}
