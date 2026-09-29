'use client';

import {ReactNode, useMemo, useRef, useState} from 'react';
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
import {FilterInput} from '@/components/ui/filter-input';
import {Skeleton, EmptyState} from '@/components/ui/misc';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {cn} from '@/lib/utils/cn';
import {
    confirmLeave,
    UnsavedChangesScope,
    useUnsavedChangesChildScope,
} from '@/lib/navigation/unsavedChanges';
import {NestedRoutePath, useRoutePath} from '@/lib/navigation/routePath';
import {useFocusFirstField} from '@/hooks/useFocusFirstField';

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
    /**
     * Render a nested management panel for the selected item (e.g. entities
     * of a list). It gets the URL below `:id/manage` (`useRoutePath`).
     */
    renderManage?: (item: D, back: () => void) => ReactNode;
    manageLabel?: string;
    /** Take the whole height of the parent (a flex column); both panes scroll */
    fill?: boolean;
};

/** Path segment of the item being created */
const NEW = 'new';
/** Path segment of the nested management panel, below the item id */
const MANAGE = 'manage';

/**
 * Generic master / detail manager used for workspace definitions (tags,
 * attribute definitions, rendition definitions, policies, integrations...):
 * filterable, sortable list on the left, form on the right.
 *
 * What is shown lives in the URL (`useRoutePath`), so that a refresh or a
 * shared link lands on it: `:id` (edited), `new`, `:id/manage/…` (the nested
 * panel).
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
    fill,
}: Props<D>) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [query, setQuery] = useState('');
    const {segments, navigate} = useRoutePath();
    const selected: string | null = segments[0] ?? null;
    const managing = segments[1] === MANAGE ? selected : null;
    const formPane = useRef<HTMLDivElement>(null);
    useFocusFirstField(formPane, selected === NEW);
    // Just created: shown until the refreshed items include it
    const [created, setCreated] = useState<D>();
    // The form pane: leaving the item being edited asks first when it is dirty
    const formScope = useUnsavedChangesChildScope();
    const leaveForm = (next: () => void) => {
        void confirmLeave(formScope).then(leave => leave && next());
    };
    const select = (id: string | null) => {
        if (id !== selected) {
            leaveForm(() => navigate(id ? [id] : []));
        }
    };
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}})
    );
    // Optimistic order, kept until the refreshed items arrive
    const [order, setOrder] = useState<{
        of: D[] | undefined;
        ids: string[];
    } | null>(null);

    const ordered = useMemo(() => {
        if (!items || order?.of !== items) {
            return items;
        }

        return order.ids
            .map(id => items.find(i => i.id === id))
            .filter((i): i is D => !!i);
    }, [items, order]);

    const visible = useMemo(() => {
        if (!ordered) {
            return [];
        }
        if (!query || !filter) {
            return ordered;
        }

        return ordered.filter(i => filter(i, query.toLowerCase()));
    }, [ordered, query, filter]);

    const current =
        selected === NEW
            ? undefined
            : (items?.find(i => i.id === selected) ??
              (created?.id === selected ? created : undefined));
    // An id in the URL that is not (or no longer) in the list
    const missing = selected !== null && selected !== NEW && !current;
    const managed = managing ? current : undefined;

    const onDragEnd = async (e: DragEndEvent) => {
        const {active, over} = e;
        if (!over || active.id === over.id || !ordered || !onSort) {
            return;
        }
        const ids = arrayMove(
            ordered.map(i => i.id),
            ordered.findIndex(i => i.id === active.id),
            ordered.findIndex(i => i.id === over.id)
        );
        setOrder({of: items, ids});
        try {
            await onSort(ids);
            onChanged();
        } catch (err: any) {
            setOrder(null);
            toast.error(err?.message);
        }
    };

    if (managed && renderManage) {
        return (
            <NestedRoutePath prefix={[managed.id, MANAGE]}>
                {renderManage(managed, () => navigate([managed.id]))}
            </NestedRoutePath>
        );
    }

    return (
        <div
            className={cn(
                // minmax(0, …): wide content (YAML, references) must not widen the columns
                'grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_minmax(0,2fr)]',
                fill && 'min-h-0 flex-1 lg:grid-rows-[minmax(0,1fr)]'
            )}
        >
            <div
                className={cn(
                    'min-w-0 space-y-2',
                    fill && 'flex min-h-0 flex-col'
                )}
            >
                <div className="flex items-center gap-2">
                    <FilterInput value={query} onValueChange={setQuery} />
                    <Button
                        size="sm"
                        data-testid="definition-create"
                        variant={selected === NEW ? 'default' : 'outline'}
                        onClick={() => select(NEW)}
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
                            <ul
                                className={cn(
                                    'space-y-1 overflow-y-auto pr-1',
                                    fill ? 'min-h-0 flex-1' : 'max-h-[60vh]'
                                )}
                            >
                                {visible.map(item => (
                                    <Row
                                        key={item.id}
                                        id={item.id}
                                        sortable={!!onSort && !query}
                                        active={selected === item.id}
                                        onClick={() => select(item.id)}
                                        onManage={
                                            renderManage
                                                ? () =>
                                                      leaveForm(() =>
                                                          navigate([
                                                              item.id,
                                                              MANAGE,
                                                          ])
                                                      )
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
                                                                      navigate(
                                                                          [],
                                                                          {
                                                                              replace: true,
                                                                          }
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
            <div
                ref={formPane}
                className={cn(
                    'min-h-64 min-w-0 rounded-md border p-4',
                    fill && 'lg:overflow-y-auto'
                )}
            >
                {missing && !items ? (
                    <Skeleton className="h-32" />
                ) : missing ? (
                    <EmptyState
                        title={t(
                            'definition.not_found',
                            'This item does not exist anymore.'
                        )}
                        className="h-full"
                    />
                ) : selected === null ? (
                    <EmptyState
                        title={t(
                            'definition.select_hint',
                            'Select an item to edit it, or create a new one.'
                        )}
                        className="h-full"
                    />
                ) : (
                    <UnsavedChangesScope.Provider value={formScope}>
                        <div key={selected}>
                            {renderForm(
                                current,
                                saved => {
                                    onChanged();
                                    setCreated(saved);
                                    // Created: `new` would open a blank form
                                    navigate([saved.id], {
                                        replace: selected === NEW,
                                    });
                                },
                                () => select(null)
                            )}
                        </div>
                    </UnsavedChangesScope.Provider>
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
            data-testid="definition-item"
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
