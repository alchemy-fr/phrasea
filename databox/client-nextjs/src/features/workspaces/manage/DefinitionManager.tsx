'use client';

import {ReactNode, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {GripVerticalIcon, PlusIcon, Trash2Icon} from 'lucide-react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {toast} from 'sonner';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {Skeleton, EmptyState} from '@/components/ui/misc';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {cn} from '@/lib/utils/cn';

export type DefinitionItem = {id: string};

type Props<D extends DefinitionItem> = {
    items: D[] | undefined;
    loading?: boolean;
    /** Render the list row (title, badges...) */
    renderItem: (item: D) => ReactNode;
    /** Render the edit / create form. `onSaved` must be called with the saved item. */
    renderForm: (
        item: D | undefined,
        onSaved: (item: D) => void,
        onCancel: () => void
    ) => ReactNode;
    onDelete?: (item: D) => Promise<void>;
    onSort?: (ids: string[]) => Promise<void>;
    onChanged: () => void;
    filter?: (item: D, query: string) => boolean;
    createLabel?: string;
    emptyLabel?: string;
    /** Extra content rendered in the list toolbar */
    toolbar?: ReactNode;
    /** Render a nested management panel for the selected item (e.g. entities of a list) */
    renderManage?: (item: D, back: () => void) => ReactNode;
    manageLabel?: string;
};

/**
 * Generic master / detail manager used for workspace definitions (tags,
 * attribute definitions, rendition definitions, policies, integrations...):
 * filterable, sortable list on the left, form on the right.
 */
export function DefinitionManager<D extends DefinitionItem>({
    items,
    loading,
    renderItem,
    renderForm,
    onDelete,
    onSort,
    onChanged,
    filter,
    createLabel,
    emptyLabel,
    toolbar,
    renderManage,
    manageLabel,
}: Props<D>) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<string | 'new' | null>(null);
    const [managing, setManaging] = useState<string | null>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}})
    );

    const visible = useMemo(() => {
        if (!items) {
            return [];
        }
        if (!query || !filter) {
            return items;
        }

        return items.filter(i => filter(i, query.toLowerCase()));
    }, [items, query, filter]);

    const current =
        selected === 'new' ? undefined : items?.find(i => i.id === selected);
    const managed = managing ? items?.find(i => i.id === managing) : undefined;

    const onDragEnd = async (e: DragEndEvent) => {
        const {active, over} = e;
        if (!over || active.id === over.id || !items || !onSort) {
            return;
        }
        const ids = arrayMove(
            items.map(i => i.id),
            items.findIndex(i => i.id === active.id),
            items.findIndex(i => i.id === over.id)
        );
        try {
            await onSort(ids);
            onChanged();
        } catch (err: any) {
            toast.error(err?.message);
        }
    };

    if (managed && renderManage) {
        return <>{renderManage(managed, () => setManaging(null))}</>;
    }

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_2fr]">
            <div className="space-y-2">
                <div className="flex items-center gap-2">
                    <Input
                        value={query}
                        onChange={e => setQuery(e.target.value)}
                        placeholder={t('common.filter', 'Filter…')}
                        className="h-8"
                    />
                    <Button
                        size="sm"
                        variant={selected === 'new' ? 'default' : 'outline'}
                        onClick={() => setSelected('new')}
                    >
                        <PlusIcon />{' '}
                        {createLabel ?? t('common.create', 'Create')}
                    </Button>
                </div>
                {toolbar}
                {loading ? (
                    <div className="space-y-1">
                        {[...Array(4)].map((_, i) => (
                            <Skeleton key={i} className="h-9" />
                        ))}
                    </div>
                ) : visible.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        {emptyLabel ??
                            t('common.empty_list', 'Nothing here yet')}
                    </p>
                ) : (
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={onDragEnd}
                    >
                        <SortableContext
                            items={visible.map(i => i.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            <ul className="max-h-[60vh] space-y-1 overflow-y-auto pr-1">
                                {visible.map(item => (
                                    <Row
                                        key={item.id}
                                        id={item.id}
                                        sortable={!!onSort && !query}
                                        active={selected === item.id}
                                        onClick={() => setSelected(item.id)}
                                        onManage={
                                            renderManage
                                                ? () => setManaging(item.id)
                                                : undefined
                                        }
                                        manageLabel={manageLabel}
                                        onDelete={
                                            onDelete
                                                ? () =>
                                                      openModal(ConfirmDialog, {
                                                          title: t(
                                                              'definition.delete.title',
                                                              'Delete this item?'
                                                          ),
                                                          destructive: true,
                                                          onConfirm:
                                                              async () => {
                                                                  await onDelete(
                                                                      item
                                                                  );
                                                                  if (
                                                                      selected ===
                                                                      item.id
                                                                  ) {
                                                                      setSelected(
                                                                          null
                                                                      );
                                                                  }
                                                                  onChanged();
                                                              },
                                                      })
                                                : undefined
                                        }
                                    >
                                        {renderItem(item)}
                                    </Row>
                                ))}
                            </ul>
                        </SortableContext>
                    </DndContext>
                )}
            </div>
            <div className="min-h-64 rounded-md border p-4">
                {selected === null ? (
                    <EmptyState
                        title={t(
                            'definition.select_hint',
                            'Select an item to edit it, or create a new one.'
                        )}
                        className="h-full"
                    />
                ) : (
                    <div key={selected}>
                        {renderForm(
                            current,
                            saved => {
                                onChanged();
                                setSelected(saved.id);
                            },
                            () => setSelected(null)
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function Row({
    id,
    sortable,
    active,
    onClick,
    onDelete,
    onManage,
    manageLabel,
    children,
}: {
    id: string;
    sortable: boolean;
    active: boolean;
    onClick: () => void;
    onDelete?: () => void;
    onManage?: () => void;
    manageLabel?: string;
    children: ReactNode;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id, disabled: !sortable});

    return (
        <li
            ref={setNodeRef}
            style={{transform: CSS.Transform.toString(transform), transition}}
            className={cn(
                'group/def flex items-center gap-1 rounded-md border bg-card pr-1 text-sm',
                active && 'border-primary bg-primary/5',
                isDragging && 'z-10 shadow-md'
            )}
        >
            {sortable ? (
                <button
                    type="button"
                    className="cursor-grab px-1 text-muted-foreground"
                    {...attributes}
                    {...listeners}
                    aria-label="Drag"
                >
                    <GripVerticalIcon className="size-4" />
                </button>
            ) : null}
            <button
                type="button"
                className="min-w-0 flex-1 px-2 py-2 text-left"
                onClick={onClick}
            >
                {children}
            </button>
            {onManage ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 text-xs"
                    onClick={onManage}
                >
                    {manageLabel}
                </Button>
            ) : null}
            {onDelete ? (
                <Button
                    variant="ghost"
                    size="icon-xs"
                    className="text-destructive opacity-0 group-hover/def:opacity-100"
                    onClick={onDelete}
                    aria-label="Delete"
                >
                    <Trash2Icon />
                </Button>
            ) : null}
        </li>
    );
}
