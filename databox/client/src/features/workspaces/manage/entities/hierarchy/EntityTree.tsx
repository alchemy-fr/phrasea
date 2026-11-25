'use client';

import {
    type ReactElement,
    type ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    GripVerticalIcon,
    ListTreeIcon,
    MoveIcon,
    PlusIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {getAttributeEntities, moveAttributeEntity} from '@/lib/api/metadata';
import {Button} from '@alchemy/next-ui/ui/button';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {EntityChip} from '@/components/chips';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {cn} from '@alchemy/next-ui/utils/cn';
import {isChildrenGridQueryKey} from '../useEntityGridQuery';
import {MoveEntityDialog} from './MoveEntityDialog';
import {
    dropZoneClass,
    HierarchyDndProvider,
    type HierarchyDrop,
    useHierarchyNode,
    useTopLevelDrop,
} from './hierarchyDnd';

const PAGE_SIZE = 200;

/** Hovering a collapsed node while dragging opens it after this delay */
const EXPAND_DELAY = 600;

export function hierarchyQueryKey(listId: string): unknown[] {
    return ['entity-hierarchy', listId];
}

export function useRefreshHierarchy(listId: string) {
    const queryClient = useQueryClient();

    return () =>
        Promise.all([
            queryClient.invalidateQueries({
                queryKey: hierarchyQueryKey(listId),
            }),
            // Not the flat values table: the tree only moves its rows. The
            // table of the children of a node (`filters.parent`) does change.
            queryClient.invalidateQueries({
                queryKey: ['attribute-entities'],
                predicate: q =>
                    q.queryKey[1] !== 'grid' ||
                    isChildrenGridQueryKey(q.queryKey),
            }),
        ]);
}

type TreeOptions = {
    list: EntityList;
    /** Wraps the row of a node, e.g. in a context menu */
    wrapRow?: (entity: AttributeEntity, row: ReactElement) => ReactElement;
    /** Double click on a node */
    onOpen?: (entity: AttributeEntity) => void;
    /** Extra buttons of a node, shown on hover */
    renderActions?: (entity: AttributeEntity) => ReactNode;
    /** The "+" of a node, or of the root (`null`): adds a child */
    onAdd?: (parent: AttributeEntity | null) => void;
    /** The selected node, `null` for the root (the top level) */
    selectedId?: string | null;
    /** A click on a node, or on the root (`null`), selects it */
    onSelect?: (entity: AttributeEntity | null) => void;
    /**
     * Opens these nodes as they load, and scrolls to the selected one: a new
     * object each time an entity is shown in the tree
     */
    reveal?: {openIds: string[]} | null;
};

/**
 * The entities of a list as a forest: the entities without parent at the top
 * level, their children loaded when a node opens. The nodes are dragged
 * beside or inside another one, or onto the free space to reach the top level.
 */
export function EntityTree({
    className,
    ...options
}: TreeOptions & {className?: string}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {list} = options;
    const refresh = useRefreshHierarchy(list.id);

    const onDrop = async (drop: HierarchyDrop) => {
        if (drop.reparent) {
            // Shows what the move affects before applying it
            openModal(MoveEntityDialog, {
                list,
                entity: drop.entity,
                parentId: drop.parentId,
                position: drop.position,
                onMoved: refresh,
            });

            return;
        }
        try {
            await moveAttributeEntity(
                drop.entity.id,
                drop.parentId,
                drop.position
            );
            await refresh();
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    return (
        <HierarchyDndProvider
            onDrop={onDrop}
            renderOverlay={e => <NodeLabel entity={e} />}
        >
            <div className={cn('flex min-h-0 flex-col gap-2', className)}>
                <p className="text-xs text-muted-foreground">
                    {t(
                        'entity_hierarchy.dnd_hint',
                        'Drag an entity onto the top or bottom of another one to place it beside it, onto its middle to place it inside, or onto the free space to move it to the top level.'
                    )}
                </p>
                <TopLevel options={options} />
            </div>
        </HierarchyDndProvider>
    );
}

function TopLevel({options}: {options: TreeOptions}) {
    const {t} = useTranslation();
    const {setNodeRef, isOver} = useTopLevelDrop();
    const {list, selectedId, onSelect} = options;

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'min-h-24 flex-1 overflow-auto rounded-md border p-2',
                isOver && dropZoneClass.inside
            )}
            data-testid="entity-tree"
        >
            {/* As wide as its widest node: deep nodes scroll horizontally */}
            <div className="w-max min-w-full">
                {/* The root: the top level, with its add button as any node */}
                <div
                    className={cn(
                        'group flex cursor-default items-center gap-1 rounded px-1 py-0.5 text-sm font-medium whitespace-nowrap hover:bg-accent/50',
                        onSelect && selectedId === null && 'bg-accent'
                    )}
                    aria-selected={onSelect ? selectedId === null : undefined}
                    onClick={
                        onSelect
                            ? e => {
                                  if (
                                      !(e.target as HTMLElement).closest(
                                          'button'
                                      )
                                  ) {
                                      onSelect(null);
                                  }
                              }
                            : undefined
                    }
                    data-testid="entity-tree-root"
                >
                    <ListTreeIcon className="size-4 text-muted-foreground" />
                    {list.name}
                    <span className="ml-auto flex gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            title={t(
                                'entity_hierarchy.add_top',
                                'Add at the top level'
                            )}
                            onClick={() => options.onAdd?.(null)}
                            data-testid="entity-tree-add-top"
                        >
                            <PlusIcon />
                        </Button>
                    </span>
                </div>
                <Children options={options} parentId={null} ancestorIds={[]} />
            </div>
        </div>
    );
}

export function NodeLabel({entity}: {entity: AttributeEntity}) {
    const {t} = useTranslation();

    return entity.placeholder ? (
        <span className="text-sm text-muted-foreground italic">
            {t('entity_hierarchy.placeholder', 'empty level')}
        </span>
    ) : (
        <EntityChip entity={entity} size="sm" />
    );
}

function TreeNode({
    options,
    entity,
    index,
    siblingCount,
    ancestorIds,
}: {
    options: TreeOptions;
    entity: AttributeEntity;
    index: number;
    siblingCount: number;
    ancestorIds: string[];
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {list, wrapRow, onOpen, renderActions, onAdd, onSelect} = options;
    const selected = !!onSelect && options.selectedId === entity.id;
    const refresh = useRefreshHierarchy(list.id);
    const [open, setOpen] = useState(false);
    const rowRef = useRef<HTMLDivElement | null>(null);
    const hasChildren = (entity.childCount ?? 0) > 0;
    const {setNodeRef, handle, isDragging, zone} = useHierarchyNode({
        entity,
        index,
        ancestorIds,
    });

    useEffect(() => {
        if (zone !== 'inside' || open || !hasChildren) {
            return;
        }
        const timeout = setTimeout(() => setOpen(true), EXPAND_DELAY);

        return () => clearTimeout(timeout);
    }, [zone, open, hasChildren]);

    const {reveal} = options;
    useEffect(() => {
        if (!reveal) {
            return;
        }
        if (reveal.openIds.includes(entity.id)) {
            setOpen(true);
        }
        if (selected) {
            rowRef.current?.scrollIntoView?.({block: 'nearest'});
        }
        // Once per reveal, or when the node loads during it
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reveal, entity.id]);

    const move = async (position: number) => {
        try {
            await moveAttributeEntity(
                entity.id,
                entity.parentId ?? null,
                position
            );
            await refresh();
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    const row = (
        <div
            ref={el => {
                setNodeRef(el);
                rowRef.current = el;
            }}
            className={cn(
                'group flex items-center gap-1 rounded px-1 py-0.5 whitespace-nowrap hover:bg-accent/50',
                selected && 'bg-accent',
                entity.status === AttributeEntityStatus.Pending &&
                    'bg-warning/10',
                entity.status === AttributeEntityStatus.Rejected &&
                    'opacity-60',
                // Its context menu is open
                'data-[state=open]:bg-accent',
                isDragging && 'opacity-40',
                zone && dropZoneClass[zone]
            )}
            aria-selected={onSelect ? selected : undefined}
            onClick={
                onSelect
                    ? e => {
                          // Not its buttons (toggle, drag, actions)
                          if (!(e.target as HTMLElement).closest('button')) {
                              onSelect(entity);
                          }
                      }
                    : undefined
            }
            onDoubleClick={onOpen ? () => onOpen(entity) : undefined}
            data-testid="entity-tree-node"
        >
            <DragHandle handle={handle} />
            <button
                type="button"
                className="flex size-5 items-center justify-center text-muted-foreground disabled:opacity-0"
                disabled={!hasChildren}
                onClick={() => setOpen(o => !o)}
                aria-label={t('common.toggle', 'Toggle')}
            >
                {open ? (
                    <ChevronDownIcon className="size-4" />
                ) : (
                    <ChevronRightIcon className="size-4" />
                )}
            </button>
            <NodeLabel entity={entity} />
            <span className="ml-auto flex gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                {renderActions?.(entity)}
                <Button
                    variant="ghost"
                    size="icon-xs"
                    title={t('entity_hierarchy.add_child', 'Add a child')}
                    onClick={() => {
                        // Shows the new child once created
                        setOpen(true);
                        onAdd?.(entity);
                    }}
                    data-testid="entity-tree-add-child"
                >
                    <PlusIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    disabled={index === 0}
                    title={t('entity_hierarchy.move_up', 'Move up')}
                    onClick={() => move(index - 1)}
                >
                    <ArrowUpIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    disabled={index >= siblingCount - 1}
                    title={t('entity_hierarchy.move_down', 'Move down')}
                    onClick={() => move(index + 1)}
                >
                    <ArrowDownIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    title={t('entity_hierarchy.move', 'Move…')}
                    onClick={() =>
                        openModal(MoveEntityDialog, {
                            list,
                            entity,
                            onMoved: refresh,
                        })
                    }
                >
                    <MoveIcon />
                </Button>
            </span>
        </div>
    );

    return (
        <div>
            {wrapRow ? wrapRow(entity, row) : row}
            {open ? (
                <div className="ml-4 border-l pl-2">
                    {hasChildren ? (
                        <Children
                            options={options}
                            parentId={entity.id}
                            ancestorIds={[...ancestorIds, entity.id]}
                        />
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

function DragHandle({handle}: {handle: object}) {
    const {t} = useTranslation();

    return (
        <button
            type="button"
            className="cursor-grab touch-none text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
            aria-label={t('entity_hierarchy.drag', 'Drag to move')}
            {...handle}
        >
            <GripVerticalIcon className="size-4" />
        </button>
    );
}

/** The children of a node, or the top level (null parent), page by page */
function Children({
    options,
    parentId,
    ancestorIds,
}: {
    options: TreeOptions;
    parentId: string | null;
    ancestorIds: string[];
}) {
    const {t} = useTranslation();
    const {list} = options;
    const children = useInfiniteQuery({
        queryKey: [...hierarchyQueryKey(list.id), 'children', parentId],
        queryFn: ({pageParam}) =>
            getAttributeEntities(
                pageParam
                    ? {url: pageParam}
                    : {
                          list: list.id,
                          ...(parentId ? {parent: parentId} : {depth: 0}),
                          // Siblings of equal position (never moved) by value
                          order: {position: 'asc', value: 'asc'},
                          limit: PAGE_SIZE,
                      }
            ),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: page => page.next,
    });

    if (!children.data) {
        return <InlineLoader />;
    }
    const items = children.data.pages.flatMap(p => p.items);
    const total = children.data.pages[0]?.total ?? items.length;

    if (items.length === 0 && !parentId) {
        return (
            <p className="p-2 text-sm text-muted-foreground">
                {t('entity.empty', 'No value')}
            </p>
        );
    }

    return (
        <>
            {items.map((child, i) => (
                <TreeNode
                    key={child.id}
                    options={options}
                    entity={child}
                    index={i}
                    siblingCount={total}
                    ancestorIds={ancestorIds}
                />
            ))}
            {children.hasNextPage ? (
                <Button
                    variant="link"
                    size="sm"
                    className="h-6 px-1 text-xs"
                    loading={children.isFetchingNextPage}
                    onClick={() => void children.fetchNextPage()}
                >
                    {t('entity_hierarchy.more_children', '{{count}} more…', {
                        count: total - items.length,
                    })}
                </Button>
            ) : null}
        </>
    );
}
