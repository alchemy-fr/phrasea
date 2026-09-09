'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    ChevronRightIcon,
    FolderIcon,
    FolderPlusIcon,
    LayersIcon,
    XIcon,
} from 'lucide-react';
import type {Collection, Workspace} from '@/types/api';
import {EntityName} from '@/types/api';
import {
    useCollectionStore,
    pagerKey,
    CollectionNode,
} from '@/features/collections/collectionStore';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {Skeleton} from '@/components/ui/misc';
import {cn} from '@/lib/utils/cn';
import {iri} from '@/lib/utils/iri';
import {postCollection} from '@/lib/api/collections';

export type TreeSelection = {
    /** IRI of the selected workspace or collection */
    iri: string;
    workspaceId: string;
    collectionId?: string;
    label: string;
};

type Capability = 'createAsset' | 'createCollection' | 'edit';

type Props = {
    value: TreeSelection | undefined;
    onChange: (selection: TreeSelection | undefined) => void;
    /** Only allow nodes with this capability */
    requireCapability?: Capability;
    /** Restrict to a workspace */
    workspaceId?: string;
    /** Disabled collection ids (and their descendants), e.g. the moved collection itself */
    disabledIds?: string[];
    allowWorkspace?: boolean;
    /** Allow creating a collection inline under the selected node */
    allowCreate?: boolean;
    className?: string;
};

/**
 * Selectable workspace / collection tree used as destination picker.
 */
export function CollectionTreePicker({
    value,
    onChange,
    requireCapability,
    workspaceId,
    disabledIds = [],
    allowWorkspace = true,
    allowCreate = false,
    className,
}: Props) {
    const {workspaces, workspacesLoaded, loadWorkspaces} = useCollectionStore();

    useEffect(() => {
        void loadWorkspaces();
    }, [loadWorkspaces]);

    const list = workspaceId
        ? workspaces.filter(w => w.id === workspaceId)
        : workspaces;

    return (
        <div
            className={cn(
                'max-h-72 overflow-y-auto rounded-md border p-1',
                className
            )}
        >
            {!workspacesLoaded ? (
                <div className="space-y-2 p-2">
                    <Skeleton className="h-5 w-1/2" />
                    <Skeleton className="h-5 w-2/3" />
                </div>
            ) : null}
            {list.map(ws => (
                <WorkspaceNode
                    key={ws.id}
                    workspace={ws}
                    value={value}
                    onChange={onChange}
                    requireCapability={requireCapability}
                    disabledIds={disabledIds}
                    allowWorkspace={allowWorkspace}
                    allowCreate={allowCreate}
                    defaultExpanded={list.length === 1}
                />
            ))}
        </div>
    );
}

function WorkspaceNode({
    workspace,
    value,
    onChange,
    requireCapability,
    disabledIds,
    allowWorkspace,
    allowCreate,
    defaultExpanded,
}: Omit<Props, 'className' | 'workspaceId'> & {
    workspace: Workspace;
    defaultExpanded: boolean;
    disabledIds: string[];
}) {
    const {pagers, collections, loadChildren, loadMore} = useCollectionStore();
    const [expanded, setExpanded] = useState(defaultExpanded);
    const pager = pagers[pagerKey(workspace.id)];
    const selectable =
        allowWorkspace &&
        (!requireCapability || workspace.capabilities[requireCapability]);
    const selected = value?.workspaceId === workspace.id && !value.collectionId;

    useEffect(() => {
        if (expanded) {
            void loadChildren(workspace.id);
        }
    }, [expanded, workspace.id, loadChildren]);

    return (
        <div>
            <div
                className={cn(
                    'flex items-center rounded',
                    selected && 'bg-primary/10'
                )}
            >
                <button
                    type="button"
                    className="flex size-7 items-center justify-center text-muted-foreground"
                    onClick={() => setExpanded(e => !e)}
                >
                    <ChevronRightIcon
                        className={cn(
                            'size-4 transition-transform',
                            expanded && 'rotate-90'
                        )}
                    />
                </button>
                <button
                    type="button"
                    disabled={!selectable}
                    className={cn(
                        'flex min-w-0 flex-1 items-center gap-2 py-1 text-left text-sm font-medium',
                        selectable
                            ? 'hover:bg-accent/60'
                            : 'cursor-default text-muted-foreground'
                    )}
                    onClick={() =>
                        onChange(
                            selected
                                ? undefined
                                : {
                                      iri: iri(
                                          EntityName.Workspace,
                                          workspace.id
                                      ),
                                      workspaceId: workspace.id,
                                      label:
                                          workspace.displayName ??
                                          workspace.name,
                                  }
                        )
                    }
                >
                    <LayersIcon className="size-4 shrink-0" />
                    <span className="truncate">
                        {workspace.displayName ?? workspace.name}
                    </span>
                </button>
            </div>
            {expanded ? (
                <div>
                    {pager?.ids.map(id =>
                        collections[id] ? (
                            <CollectionTreeNode
                                key={id}
                                node={collections[id]}
                                depth={1}
                                value={value}
                                onChange={onChange}
                                requireCapability={requireCapability}
                                disabledIds={disabledIds}
                                allowCreate={allowCreate}
                                disabledByAncestor={false}
                            />
                        ) : null
                    )}
                    {pager?.next ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-7 h-7 text-xs"
                            onClick={() => loadMore(workspace.id)}
                            loading={pager.loading}
                        >
                            …
                        </Button>
                    ) : null}
                    {allowCreate && workspace.capabilities.createCollection ? (
                        <InlineCreate
                            workspaceId={workspace.id}
                            depth={1}
                            onCreated={c =>
                                onChange(toSelection(c, workspace.id))
                            }
                        />
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

function toSelection(c: Collection, workspaceId: string): TreeSelection {
    return {
        iri: iri(EntityName.Collection, c.id),
        workspaceId,
        collectionId: c.id,
        label: c.absoluteDisplayName ?? c.displayName ?? c.name,
    };
}

function CollectionTreeNode({
    node,
    depth,
    value,
    onChange,
    requireCapability,
    disabledIds,
    allowCreate,
    disabledByAncestor,
}: {
    node: CollectionNode;
    depth: number;
    value: TreeSelection | undefined;
    onChange: (s: TreeSelection | undefined) => void;
    requireCapability?: Capability;
    disabledIds: string[];
    allowCreate?: boolean;
    disabledByAncestor: boolean;
}) {
    const {pagers, collections, loadChildren, loadMore} = useCollectionStore();
    const [expanded, setExpanded] = useState(false);
    const pager = pagers[pagerKey(node.workspaceId, node.id)];
    const disabled = disabledByAncestor || disabledIds.includes(node.id);
    const selectable =
        !disabled &&
        !node.deleted &&
        (!requireCapability || node.capabilities[requireCapability]);
    const selected = value?.collectionId === node.id;
    const hasChildren = pager ? pager.ids.length > 0 || !pager.loaded : true;

    useEffect(() => {
        if (expanded) {
            void loadChildren(node.workspaceId, node.id);
        }
    }, [expanded, node.workspaceId, node.id, loadChildren]);

    return (
        <div>
            <div
                className={cn(
                    'flex items-center rounded',
                    selected && 'bg-primary/10'
                )}
                style={{paddingLeft: depth * 12}}
            >
                <button
                    type="button"
                    className={cn(
                        'flex size-7 items-center justify-center text-muted-foreground',
                        !hasChildren && 'invisible'
                    )}
                    onClick={() => setExpanded(e => !e)}
                >
                    <ChevronRightIcon
                        className={cn(
                            'size-4 transition-transform',
                            expanded && 'rotate-90'
                        )}
                    />
                </button>
                <button
                    type="button"
                    disabled={!selectable}
                    className={cn(
                        'flex min-w-0 flex-1 items-center gap-2 py-1 text-left text-sm',
                        selectable
                            ? 'hover:bg-accent/60'
                            : 'cursor-default text-muted-foreground/60'
                    )}
                    onClick={() =>
                        onChange(
                            selected
                                ? undefined
                                : toSelection(node, node.workspaceId)
                        )
                    }
                >
                    <FolderIcon className="size-4 shrink-0" />
                    <span className="truncate">
                        {node.displayName ?? node.name}
                    </span>
                </button>
            </div>
            {expanded ? (
                <div>
                    {pager?.ids.map(id =>
                        collections[id] ? (
                            <CollectionTreeNode
                                key={id}
                                node={collections[id]}
                                depth={depth + 1}
                                value={value}
                                onChange={onChange}
                                requireCapability={requireCapability}
                                disabledIds={disabledIds}
                                allowCreate={allowCreate}
                                disabledByAncestor={disabled}
                            />
                        ) : null
                    )}
                    {pager?.next ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            style={{marginLeft: (depth + 1) * 12 + 28}}
                            onClick={() => loadMore(node.workspaceId, node.id)}
                            loading={pager.loading}
                        >
                            …
                        </Button>
                    ) : null}
                    {allowCreate &&
                    node.capabilities.createCollection &&
                    !disabled ? (
                        <InlineCreate
                            workspaceId={node.workspaceId}
                            parentId={node.id}
                            depth={depth + 1}
                            onCreated={c =>
                                onChange(toSelection(c, node.workspaceId))
                            }
                        />
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

function InlineCreate({
    workspaceId,
    parentId,
    depth,
    onCreated,
}: {
    workspaceId: string;
    parentId?: string;
    depth: number;
    onCreated: (c: Collection) => void;
}) {
    const {t} = useTranslation();
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState('');
    const [loading, setLoading] = useState(false);
    const upsert = useCollectionStore(s => s.upsertCollection);

    const submit = async () => {
        if (!name.trim()) {
            return;
        }
        setLoading(true);
        try {
            const c = await postCollection({
                name: name.trim(),
                workspace: iri(EntityName.Workspace, workspaceId),
                parent: parentId
                    ? iri(EntityName.Collection, parentId)
                    : undefined,
            });
            upsert(c, workspaceId);
            onCreated(c);
            setEditing(false);
            setName('');
        } finally {
            setLoading(false);
        }
    };

    if (!editing) {
        return (
            <button
                type="button"
                className="flex items-center gap-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                style={{paddingLeft: depth * 12 + 28}}
                onClick={() => setEditing(true)}
            >
                <FolderPlusIcon className="size-3.5" />{' '}
                {t('collections.new_collection', 'New collection')}
            </button>
        );
    }

    return (
        <div
            className="flex items-center gap-1 py-1"
            style={{paddingLeft: depth * 12 + 28}}
        >
            <Input
                autoFocus
                value={name}
                disabled={loading}
                onChange={e => setName(e.target.value)}
                onKeyDown={e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        void submit();
                    } else if (e.key === 'Escape') {
                        setEditing(false);
                        setName('');
                    }
                }}
                placeholder={t('collections.new_collection', 'New collection')}
                className="h-7 text-xs"
            />
            <Button
                variant="ghost"
                size="icon-xs"
                onClick={() => setEditing(false)}
            >
                <XIcon />
            </Button>
        </div>
    );
}
