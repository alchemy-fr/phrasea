'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    DownloadIcon,
    GripVerticalIcon,
    MoveIcon,
    PlusIcon,
    UndoIcon,
    UploadIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeEntity, EntityList} from '@/types/api';
import {EntityName} from '@/types/api';
import {
    exportEntityHierarchy,
    getAttributeEntities,
    getAttributeEntity,
    getEntityList,
    moveAttributeEntity,
    postAttributeEntity,
    setEntityListRoot,
} from '@/lib/api/metadata';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {Badge} from '@/components/ui/misc';
import {InlineLoader} from '@/components/ui/loader';
import {EntityChip} from '@/components/chips';
import {AttributeEntitySelect} from '@/components/form/selects';
import {useModals, type ModalProps} from '@/components/modals/ModalProvider';
import {iri} from '@/lib/utils/iri';
import {cn} from '@/lib/utils/cn';
import {downloadUrl} from '@/lib/utils/misc';
import {ImportEntityHierarchyDialog} from './ImportEntityHierarchyDialog';
import {MoveEntityDialog} from './MoveEntityDialog';
import {
    dropZoneClass,
    HierarchyDndProvider,
    type HierarchyDrop,
    useHierarchyNode,
    useUnclassifiedDrop,
    useUnclassifiedEntityDrag,
} from './hierarchyDnd';

const CHILDREN_LIMIT = 200;

/** Hovering a collapsed node while dragging opens it after this delay */
const EXPAND_DELAY = 600;

export function hierarchyQueryKey(listId: string): unknown[] {
    return ['entity-hierarchy', listId];
}

/**
 * Organizes the existing entities of a list in a tree: the root, the nodes
 * (placeholders fill the empty levels) and the unclassified entities, which
 * hierarchical attributes never propose.
 */
export function EntityHierarchyDialog({
    open,
    onOpenChange,
    list: initialList,
}: ModalProps & {list: EntityList}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const list = useQuery({
        queryKey: [...hierarchyQueryKey(initialList.id), 'list'],
        queryFn: () => getEntityList(initialList.id),
        initialData: initialList,
    }).data;
    const rootId = list.rootEntityId ?? undefined;
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

    const exportCsv = async () => {
        const {blob, filename} = await exportEntityHierarchy(list.id);
        downloadUrl(URL.createObjectURL(blob), filename);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="xl" data-testid="entity-hierarchy-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {t('entity_hierarchy.title', 'Hierarchy of {{name}}', {
                            name: list.name,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'entity_hierarchy.description',
                            'Entities of the same concept (cities, streets…) must be at the same level: add an empty level where one is missing.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody className="space-y-4">
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                openModal(ImportEntityHierarchyDialog, {list})
                            }
                        >
                            <UploadIcon />{' '}
                            {t('entity_hierarchy.import', 'Import')}
                        </Button>
                        {rootId ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={exportCsv}
                            >
                                <DownloadIcon />{' '}
                                {t('entity_hierarchy.export', 'Export')}
                            </Button>
                        ) : null}
                    </div>
                    <HierarchyDndProvider
                        onDrop={onDrop}
                        renderOverlay={e => <NodeLabel entity={e} />}
                    >
                        {rootId ? (
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'entity_hierarchy.dnd_hint',
                                    'Drag an entity onto the top or bottom of another one to place it beside it, onto its middle to place it inside, or onto the unclassified panel to take it out of the hierarchy.'
                                )}
                            </p>
                        ) : null}
                        <div className="grid gap-4 md:grid-cols-[2fr_1fr]">
                            <div className="max-h-[60vh] overflow-auto rounded-md border p-2">
                                {rootId ? (
                                    <RootNode list={list} rootId={rootId} />
                                ) : (
                                    <ChooseRoot list={list} />
                                )}
                            </div>
                            <UnclassifiedPanel list={list} flat={!rootId} />
                        </div>
                    </HierarchyDndProvider>
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}

function useRefreshHierarchy(listId: string) {
    const queryClient = useQueryClient();

    return () =>
        Promise.all([
            queryClient.invalidateQueries({
                queryKey: hierarchyQueryKey(listId),
            }),
            queryClient.invalidateQueries({queryKey: ['attribute-entities']}),
        ]);
}

function useSetRoot(list: EntityList) {
    const refresh = useRefreshHierarchy(list.id);
    const [saving, setSaving] = useState(false);

    const setRoot = async (id: string) => {
        setSaving(true);
        try {
            await setEntityListRoot(list.id, id);
            await refresh();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return {setRoot, saving};
}

/** Takes the place of the tree while the list is flat */
function ChooseRoot({list}: {list: EntityList}) {
    const {t} = useTranslation();
    const {setRoot, saving} = useSetRoot(list);
    const [entityId, setEntityId] = useState<string>();
    const [value, setValue] = useState('');

    return (
        <div className="space-y-3 p-2">
            <p className="text-sm text-muted-foreground">
                {t(
                    'entity_hierarchy.no_root',
                    'The list is flat. Choose or create the entity at the top of the hierarchy (e.g. "World").'
                )}
            </p>
            <div className="flex flex-wrap items-center gap-2">
                <div className="min-w-64 flex-1">
                    <AttributeEntitySelect
                        listId={list.id}
                        value={entityId}
                        onChange={setEntityId}
                    />
                </div>
                <Button
                    size="sm"
                    disabled={!entityId}
                    loading={saving}
                    onClick={() => setRoot(entityId!)}
                >
                    {t('entity_hierarchy.set_root', 'Set as root')}
                </Button>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    className="min-w-64 flex-1"
                    value={value}
                    placeholder={t(
                        'entity_hierarchy.new_root',
                        'New root value'
                    )}
                    onChange={e => setValue(e.target.value)}
                />
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!value.trim()}
                    loading={saving}
                    onClick={async () => {
                        const created = await postAttributeEntity(list.id, {
                            value: value.trim(),
                        });
                        await setRoot(created.id);
                    }}
                >
                    <PlusIcon /> {t('common.create', 'Create')}
                </Button>
            </div>
        </div>
    );
}

function RootNode({list, rootId}: {list: EntityList; rootId: string}) {
    const root = useQuery({
        queryKey: [...hierarchyQueryKey(list.id), 'entity', rootId],
        queryFn: () => getAttributeEntity(rootId),
    });

    if (!root.data) {
        return <InlineLoader />;
    }

    return (
        <TreeNode
            list={list}
            entity={root.data}
            isRoot
            defaultOpen
            index={0}
            siblingCount={1}
            ancestorIds={[]}
        />
    );
}

function NodeLabel({entity}: {entity: AttributeEntity}) {
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
    list,
    entity,
    isRoot,
    defaultOpen,
    index,
    siblingCount,
    ancestorIds,
}: {
    list: EntityList;
    entity: AttributeEntity;
    isRoot?: boolean;
    defaultOpen?: boolean;
    index: number;
    siblingCount: number;
    ancestorIds: string[];
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const refresh = useRefreshHierarchy(list.id);
    const [open, setOpen] = useState(!!defaultOpen);
    const [adding, setAdding] = useState(false);
    const hasChildren = isRoot || (entity.childCount ?? 0) > 0;
    const {setNodeRef, handle, isDragging, zone} = useHierarchyNode({
        entity,
        index,
        ancestorIds,
        isRoot: !!isRoot,
    });

    useEffect(() => {
        if (zone !== 'inside' || open || !hasChildren) {
            return;
        }
        const timeout = setTimeout(() => setOpen(true), EXPAND_DELAY);

        return () => clearTimeout(timeout);
    }, [zone, open, hasChildren]);

    const move = async (parentId: string | null, position: number) => {
        try {
            await moveAttributeEntity(entity.id, parentId, position);
            await refresh();
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    return (
        <div>
            <div
                ref={setNodeRef}
                className={cn(
                    'group flex items-center gap-1 rounded px-1 py-0.5 hover:bg-accent/50',
                    isDragging && 'opacity-40',
                    zone && dropZoneClass[zone]
                )}
            >
                {isRoot ? null : <DragHandle handle={handle} />}
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
                <Badge variant="muted" className="ml-1">
                    {entity.hierarchyDepth}
                </Badge>
                <span className="ml-auto flex gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        title={t('entity_hierarchy.add_child', 'Add a child')}
                        onClick={() => {
                            setAdding(true);
                            setOpen(true);
                        }}
                    >
                        <PlusIcon />
                    </Button>
                    {!isRoot ? (
                        <>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                disabled={index === 0}
                                title={t('entity_hierarchy.move_up', 'Move up')}
                                onClick={() =>
                                    move(entity.parentId ?? null, index - 1)
                                }
                            >
                                <ArrowUpIcon />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                disabled={index >= siblingCount - 1}
                                title={t(
                                    'entity_hierarchy.move_down',
                                    'Move down'
                                )}
                                onClick={() =>
                                    move(entity.parentId ?? null, index + 1)
                                }
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
                        </>
                    ) : null}
                </span>
            </div>
            {open ? (
                <div className="ml-4 border-l pl-2">
                    {adding ? (
                        <AddChild
                            list={list}
                            parent={entity}
                            onDone={() => setAdding(false)}
                        />
                    ) : null}
                    {hasChildren ? (
                        <Children
                            list={list}
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

function Children({
    list,
    parentId,
    ancestorIds,
}: {
    list: EntityList;
    parentId: string;
    ancestorIds: string[];
}) {
    const {t} = useTranslation();
    const children = useQuery({
        queryKey: [...hierarchyQueryKey(list.id), 'children', parentId],
        queryFn: () =>
            getAttributeEntities({
                list: list.id,
                parent: parentId,
                order: {position: 'asc'},
                limit: CHILDREN_LIMIT,
            }),
    });

    if (!children.data) {
        return <InlineLoader />;
    }
    const items = children.data.items;

    return (
        <>
            {items.map((child, i) => (
                <TreeNode
                    key={child.id}
                    list={list}
                    entity={child}
                    index={i}
                    siblingCount={items.length}
                    ancestorIds={ancestorIds}
                />
            ))}
            {children.data.total > items.length ? (
                <p className="px-1 text-xs text-muted-foreground">
                    {t('entity_hierarchy.more_children', '{{count}} more…', {
                        count: children.data.total - items.length,
                    })}
                </p>
            ) : null}
        </>
    );
}

function AddChild({
    list,
    parent,
    onDone,
}: {
    list: EntityList;
    parent: AttributeEntity;
    onDone: () => void;
}) {
    const {t} = useTranslation();
    const refresh = useRefreshHierarchy(list.id);
    const [value, setValue] = useState('');
    const [existing, setExisting] = useState<string>();
    const [placeholder, setPlaceholder] = useState(false);
    const [saving, setSaving] = useState(false);

    const submit = async () => {
        setSaving(true);
        try {
            if (existing) {
                await moveAttributeEntity(
                    existing,
                    parent.id,
                    Number.MAX_SAFE_INTEGER
                );
            } else {
                await postAttributeEntity(list.id, {
                    value: placeholder ? '—' : value.trim(),
                    placeholder,
                    parent: iri(EntityName.AttributeEntity, parent.id),
                } as Partial<AttributeEntity>);
            }
            await refresh();
            onDone();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="my-1 space-y-2 rounded-md border bg-muted/30 p-2">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    autoFocus
                    className="h-8 min-w-48 flex-1"
                    value={value}
                    disabled={placeholder || !!existing}
                    placeholder={t('entity_hierarchy.new_value', 'New value')}
                    onChange={e => setValue(e.target.value)}
                    onKeyDown={e => {
                        if (e.key === 'Enter' && value.trim()) {
                            void submit();
                        }
                    }}
                />
                <span className="text-xs text-muted-foreground">
                    {t('common.or', 'or')}
                </span>
                <div className="min-w-48 flex-1">
                    <AttributeEntitySelect
                        listId={list.id}
                        value={existing}
                        disabled={placeholder}
                        onChange={setExisting}
                    />
                </div>
            </div>
            <div className="flex items-center justify-between gap-2">
                <LabeledControl
                    label={t(
                        'entity_hierarchy.add_placeholder',
                        'Empty level (placeholder)'
                    )}
                >
                    <Checkbox
                        checked={placeholder}
                        onCheckedChange={v => setPlaceholder(v === true)}
                    />
                </LabeledControl>
                <span className="flex gap-2">
                    <Button size="sm" variant="ghost" onClick={onDone}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        size="sm"
                        loading={saving}
                        disabled={!placeholder && !existing && !value.trim()}
                        onClick={submit}
                    >
                        {t('common.add', 'Add')}
                    </Button>
                </span>
            </div>
        </div>
    );
}

/**
 * Entities placed nowhere: still available to the attributes that are not
 * bound to the hierarchy.
 */
function UnclassifiedPanel({
    list,
    flat,
}: {
    list: EntityList;
    /** No root yet: the entities can only become the root */
    flat: boolean;
}) {
    const {t} = useTranslation();
    const [query, setQuery] = useState('');
    const {setNodeRef, isOver} = useUnclassifiedDrop();
    const unclassified = useQuery({
        queryKey: [...hierarchyQueryKey(list.id), 'unclassified', query],
        queryFn: () =>
            getAttributeEntities({
                list: list.id,
                unclassified: true,
                value: query || undefined,
                limit: 50,
            }),
    });

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'flex max-h-[60vh] flex-col gap-2 rounded-md border p-2',
                isOver && dropZoneClass.inside
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="text-sm font-medium">
                    {t('entity_hierarchy.unclassified', 'Unclassified')}
                </span>
                {unclassified.data ? (
                    <Badge variant="muted">{unclassified.data.total}</Badge>
                ) : null}
            </div>
            <Input
                className="h-8"
                value={query}
                placeholder={t('common.search', 'Search…')}
                onChange={e => setQuery(e.target.value)}
            />
            <div className="flex-1 space-y-1 overflow-auto">
                {unclassified.data?.items.map(e => (
                    <UnclassifiedEntity
                        key={e.id}
                        list={list}
                        entity={e}
                        flat={flat}
                    />
                ))}
                {unclassified.data?.items.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'entity_hierarchy.all_classified',
                            'Every entity is placed in the hierarchy.'
                        )}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

function UnclassifiedEntity({
    list,
    entity,
    flat,
}: {
    list: EntityList;
    entity: AttributeEntity;
    flat: boolean;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const refresh = useRefreshHierarchy(list.id);
    const {setRoot, saving} = useSetRoot(list);
    const {setNodeRef, handle, isDragging} = useUnclassifiedEntityDrag(
        entity,
        flat
    );

    if (flat) {
        return (
            <div className="group flex items-center gap-1 rounded px-1 py-0.5 hover:bg-accent/50">
                <EntityChip entity={entity} size="sm" />
                <Button
                    variant="ghost"
                    size="sm"
                    className="ml-auto h-7 opacity-0 group-hover:opacity-100 focus-visible:opacity-100"
                    loading={saving}
                    onClick={() => setRoot(entity.id)}
                >
                    {t('entity_hierarchy.set_root', 'Set as root')}
                </Button>
            </div>
        );
    }

    return (
        <div
            ref={setNodeRef}
            className={cn(
                'group flex items-center gap-1 rounded px-1 py-0.5 hover:bg-accent/50',
                isDragging && 'opacity-40'
            )}
        >
            <DragHandle handle={handle} />
            <EntityChip entity={entity} size="sm" />
            <Button
                variant="ghost"
                size="icon-xs"
                className="ml-auto opacity-0 group-hover:opacity-100"
                title={t('entity_hierarchy.place', 'Place…')}
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
            {entity.parentId ? (
                <Button
                    variant="ghost"
                    size="icon-xs"
                    title={t(
                        'entity_hierarchy.detach',
                        'Detach from its former parent'
                    )}
                    onClick={async () => {
                        await moveAttributeEntity(entity.id, null, 0);
                        await refresh();
                    }}
                >
                    <UndoIcon />
                </Button>
            ) : null}
        </div>
    );
}
