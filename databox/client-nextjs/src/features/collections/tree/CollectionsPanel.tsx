'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {useQuery} from '@tanstack/react-query';
import {
    BellIcon,
    ChevronRightIcon,
    FolderIcon,
    FolderOpenIcon,
    FolderPlusIcon,
    ImagePlusIcon,
    LayersIcon,
    MoreVerticalIcon,
    PencilIcon,
    RotateCcwIcon,
    Share2Icon,
    ShieldAlertIcon,
    Trash2Icon,
    BookOpenIcon,
    SettingsIcon,
} from 'lucide-react';
import type {Collection, Workspace} from '@/types/api';
import {useCollectionStore, pagerKey} from '../collectionStore';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {BuiltInAttribute, quoteAQL} from '@/features/search/searchState';
import {Button} from '@/components/ui/button';
import {Input} from '@/components/ui/input';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {Skeleton} from '@/components/ui/misc';
import {useModals} from '@/components/modals/ModalProvider';
import {routes} from '@/lib/routes';
import {cn} from '@/lib/utils/cn';
import {useAuth} from '@/lib/auth/AuthProvider';
import {UploadDialog} from '@/features/upload/UploadDialog';
import {CreateCollectionDialog} from '../CreateCollectionDialog';
import {
    DeleteCollectionDialog,
    RestoreCollectionDialog,
} from '../CollectionDialogs';
import {getCollections} from '@/lib/api/collections';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {Highlight} from '@/components/ui/highlight';
import {WorkspaceChip} from '@/components/chips';
import {PanelSection} from '@/components/layout/PanelSection';

export function CollectionsPanel() {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const search = useOptionalSearch();
    const {workspaces, workspacesLoaded, loadWorkspaces} = useCollectionStore();
    const [query, setQuery] = useState('');
    const debounced = useDebouncedValue(query, 250);

    useEffect(() => {
        void loadWorkspaces();
    }, [loadWorkspaces]);

    const results = useQuery({
        queryKey: ['collections', 'search', debounced],
        queryFn: () => getCollections({query: debounced, limit: 50}),
        enabled: debounced.trim().length > 0,
    });

    const selectTrash = () =>
        search?.resetWithCondition({
            id: BuiltInAttribute.Deleted,
            query: `${BuiltInAttribute.Deleted} = true`,
        });
    const selectQuarantine = () =>
        search?.resetWithCondition({
            id: BuiltInAttribute.AssetStatus,
            query: `${BuiltInAttribute.AssetStatus} = 2`,
        });

    return (
        <PanelSection
            title={t('collections.panel.title', 'Workspaces & collections')}
            icon={<LayersIcon />}
        >
            <div className="px-2 pb-2">
                <Input
                    value={query}
                    onChange={e => setQuery(e.target.value)}
                    placeholder={t('collections.search', 'Search collections…')}
                    className="h-8"
                />
            </div>
            {debounced ? (
                <ul className="pb-2">
                    {results.isLoading ? (
                        <li className="px-3 py-2">
                            <Skeleton className="h-6" />
                        </li>
                    ) : null}
                    {results.data?.items.length === 0 ? (
                        <li className="px-3 py-2 text-xs text-muted-foreground">
                            {t('common.no_match', 'No match')}
                        </li>
                    ) : null}
                    {results.data?.items.map(c => (
                        <li key={c.id}>
                            <button
                                type="button"
                                className={cn(
                                    'flex w-full flex-col gap-0.5 px-3 py-1.5 text-left text-sm hover:bg-accent',
                                    search?.collections.includes(c.id) &&
                                        'bg-primary/10'
                                )}
                                onClick={() =>
                                    search?.selectCollection(c.id, c)
                                }
                            >
                                <span className="flex items-center gap-1.5 truncate">
                                    <FolderIcon className="size-4 shrink-0 text-muted-foreground" />
                                    <Highlight
                                        text={
                                            c.nameHighlight ||
                                            c.displayName ||
                                            c.name
                                        }
                                    />
                                </span>
                                <span className="flex items-center gap-1 pl-5 text-[11px] text-muted-foreground">
                                    <WorkspaceChip workspace={c.workspace} />
                                    {c.absoluteDisplayName &&
                                    c.absoluteDisplayName !== c.displayName ? (
                                        <span className="truncate">
                                            {c.absoluteDisplayName}
                                        </span>
                                    ) : null}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            ) : (
                <div className="pb-2">
                    {!workspacesLoaded ? (
                        <div className="space-y-2 px-3 py-2">
                            {[...Array(3)].map((_, i) => (
                                <Skeleton key={i} className="h-6" />
                            ))}
                        </div>
                    ) : null}
                    {workspaces.map(ws => (
                        <WorkspaceItem key={ws.id} workspace={ws} />
                    ))}
                    {isAuthenticated ? (
                        <div className="mt-2 border-t pt-1">
                            <button
                                type="button"
                                className={cn(
                                    'flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent',
                                    search?.conditions.some(
                                        c =>
                                            c.id === BuiltInAttribute.Deleted &&
                                            !c.disabled
                                    ) && 'bg-primary/10'
                                )}
                                onClick={selectTrash}
                            >
                                <Trash2Icon className="size-4 text-muted-foreground" />{' '}
                                {t('collections.trash', 'Trash')}
                            </button>
                            <button
                                type="button"
                                className={cn(
                                    'flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent',
                                    search?.conditions.some(
                                        c =>
                                            c.id ===
                                                BuiltInAttribute.AssetStatus &&
                                            !c.disabled
                                    ) && 'bg-primary/10'
                                )}
                                onClick={selectQuarantine}
                            >
                                <ShieldAlertIcon className="size-4 text-muted-foreground" />{' '}
                                {t('collections.quarantine', 'Quarantine')}
                            </button>
                        </div>
                    ) : null}
                </div>
            )}
        </PanelSection>
    );
}

function WorkspaceItem({workspace}: {workspace: Workspace}) {
    const {t} = useTranslation();
    const router = useRouter();
    const search = useOptionalSearch();
    const {openModal} = useModals();
    const {pagers, collections, loadChildren, loadMore} = useCollectionStore();
    const key = pagerKey(workspace.id);
    const pager = pagers[key];
    const [expanded, setExpanded] = useState(true);
    const selected = search?.workspaces.includes(workspace.id) ?? false;

    useEffect(() => {
        if (expanded) {
            void loadChildren(workspace.id);
        }
    }, [expanded, workspace.id, loadChildren]);

    const menu = (
        Item: typeof DropdownMenuItem,
        Sep: typeof DropdownMenuSeparator
    ) => (
        <>
            {workspace.capabilities.createCollection ? (
                <Item
                    onSelect={() =>
                        openModal(CreateCollectionDialog, {
                            workspaceId: workspace.id,
                        })
                    }
                >
                    <FolderPlusIcon />{' '}
                    {t('collections.menu.add_collection', 'Add collection')}
                </Item>
            ) : null}
            {workspace.capabilities.createAsset ? (
                <Item
                    onSelect={() =>
                        openModal(UploadDialog, {workspaceId: workspace.id})
                    }
                >
                    <ImagePlusIcon />{' '}
                    {t('collections.menu.add_asset', 'Add asset')}
                </Item>
            ) : null}
            {workspace.capabilities.edit ||
            workspace.capabilities.editPermissions ? (
                <>
                    <Sep />
                    <Item
                        onSelect={() =>
                            router.push(
                                routes.workspaceManage(
                                    workspace.id,
                                    workspace.capabilities.edit
                                        ? 'edit'
                                        : 'permissions'
                                )
                            )
                        }
                    >
                        <SettingsIcon />{' '}
                        {t(
                            'collections.menu.manage_workspace',
                            'Manage workspace'
                        )}
                    </Item>
                </>
            ) : null}
        </>
    );

    return (
        <div>
            <ContextMenu>
                <ContextMenuTrigger asChild>
                    <div
                        className={cn(
                            'group/ws sticky top-0 z-10 flex items-center bg-sidebar pr-1',
                            selected && 'bg-primary/10'
                        )}
                    >
                        <button
                            type="button"
                            className="flex size-7 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-accent"
                            onClick={() => setExpanded(e => !e)}
                            onDoubleClick={() =>
                                loadChildren(workspace.id, undefined, true)
                            }
                            aria-label={expanded ? 'Collapse' : 'Expand'}
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
                            className="flex min-w-0 flex-1 items-center gap-2 py-1.5 pr-1 text-left text-sm font-semibold hover:bg-accent/60"
                            onClick={() =>
                                search?.selectWorkspace(
                                    selected ? undefined : workspace.id,
                                    workspace
                                )
                            }
                        >
                            <LayersIcon className="size-4 shrink-0 text-muted-foreground" />
                            <span className="truncate">
                                {workspace.displayName ?? workspace.name}
                            </span>
                        </button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    className="opacity-0 group-hover/ws:opacity-100 data-[state=open]:opacity-100"
                                >
                                    <MoreVerticalIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {menu(DropdownMenuItem, DropdownMenuSeparator)}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </ContextMenuTrigger>
                <ContextMenuContent>
                    {menu(ContextMenuItem as any, ContextMenuSeparator as any)}
                </ContextMenuContent>
            </ContextMenu>
            {expanded ? (
                <div>
                    {pager?.loading && pager.ids.length === 0 ? (
                        <div className="space-y-1 px-3 py-1">
                            <Skeleton className="h-5 w-2/3" />
                            <Skeleton className="h-5 w-1/2" />
                        </div>
                    ) : null}
                    {pager?.ids.map(
                        id =>
                            collections[id] && (
                                <CollectionItem
                                    key={id}
                                    collection={collections[id]}
                                    depth={1}
                                />
                            )
                    )}
                    {pager?.next ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-7 h-7 text-xs"
                            onClick={() => loadMore(workspace.id)}
                            loading={pager.loading}
                        >
                            {t('common.load_more', 'Load more')}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

function CollectionItem({
    collection,
    depth,
}: {
    collection: Collection & {workspaceId: string};
    depth: number;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const search = useOptionalSearch();
    const {openModal} = useModals();
    const {pagers, collections, loadChildren, loadMore} = useCollectionStore();
    const key = pagerKey(collection.workspaceId, collection.id);
    const pager = pagers[key];
    const [expanded, setExpanded] = useState(false);
    const selected = search?.collections.includes(collection.id) ?? false;
    const hasChildren = pager ? pager.ids.length > 0 || !pager.loaded : true;

    useEffect(() => {
        if (expanded) {
            void loadChildren(collection.workspaceId, collection.id);
        }
    }, [expanded, collection.workspaceId, collection.id, loadChildren]);

    const menu = (
        Item: typeof DropdownMenuItem,
        Sep: typeof DropdownMenuSeparator
    ) => (
        <>
            <Item
                onSelect={() =>
                    router.push(
                        routes.collectionManage(collection.id, 'notifications')
                    )
                }
            >
                <BellIcon />{' '}
                {t('collections.menu.notifications', 'Notifications')}
            </Item>
            {collection.capabilities.createAsset ? (
                <Item
                    onSelect={() =>
                        openModal(UploadDialog, {
                            workspaceId: collection.workspaceId,
                            collectionId: collection.id,
                        })
                    }
                >
                    <ImagePlusIcon />{' '}
                    {t('collections.menu.add_asset', 'Add asset')}
                </Item>
            ) : null}
            {collection.capabilities.createCollection ? (
                <Item
                    onSelect={() =>
                        openModal(CreateCollectionDialog, {
                            workspaceId: collection.workspaceId,
                            parent: collection,
                        })
                    }
                >
                    <FolderPlusIcon />{' '}
                    {t(
                        'collections.menu.add_sub_collection',
                        'Create sub-collection'
                    )}
                </Item>
            ) : null}
            {collection.capabilities.edit ? (
                <Item
                    onSelect={() =>
                        router.push(
                            routes.collectionManage(collection.id, 'edit')
                        )
                    }
                >
                    <PencilIcon /> {t('common.edit', 'Edit')}
                </Item>
            ) : null}
            {collection.capabilities.delete ? (
                <>
                    <Sep />
                    {collection.deleted ? (
                        <Item
                            onSelect={() =>
                                openModal(RestoreCollectionDialog, {collection})
                            }
                        >
                            <RotateCcwIcon /> {t('common.restore', 'Restore')}
                        </Item>
                    ) : (
                        <Item
                            variant="destructive"
                            onSelect={() =>
                                openModal(DeleteCollectionDialog, {collection})
                            }
                        >
                            <Trash2Icon /> {t('common.delete', 'Delete')}
                        </Item>
                    )}
                </>
            ) : null}
        </>
    );

    const Icon = collection.storyAsset
        ? BookOpenIcon
        : expanded
          ? FolderOpenIcon
          : FolderIcon;

    return (
        <div>
            <ContextMenu>
                <ContextMenuTrigger asChild>
                    <div
                        className={cn(
                            'group/col flex items-center pr-1',
                            selected && 'bg-primary/10'
                        )}
                        style={{paddingLeft: depth * 12}}
                    >
                        <button
                            type="button"
                            className={cn(
                                'flex size-7 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-accent',
                                !hasChildren && 'invisible'
                            )}
                            onClick={() => setExpanded(e => !e)}
                            onDoubleClick={() =>
                                loadChildren(
                                    collection.workspaceId,
                                    collection.id,
                                    true
                                )
                            }
                            aria-label={expanded ? 'Collapse' : 'Expand'}
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
                            className="flex min-w-0 flex-1 items-center gap-2 py-1 pr-1 text-left text-sm hover:bg-accent/60"
                            onClick={() =>
                                search?.selectCollection(
                                    selected ? undefined : collection.id,
                                    collection
                                )
                            }
                            title={collection.absoluteDisplayName}
                        >
                            <Icon
                                className={cn(
                                    'size-4 shrink-0',
                                    collection.public
                                        ? 'text-muted-foreground'
                                        : 'fill-muted-foreground/30 text-muted-foreground'
                                )}
                            />
                            <span
                                className={cn(
                                    'truncate',
                                    collection.deleted &&
                                        'line-through opacity-60'
                                )}
                            >
                                {collection.displayName ?? collection.name}
                            </span>
                            {collection.shared ? (
                                <Share2Icon className="size-3 shrink-0 text-muted-foreground" />
                            ) : null}
                        </button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    className="opacity-0 group-hover/col:opacity-100 data-[state=open]:opacity-100"
                                >
                                    <MoreVerticalIcon />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {menu(DropdownMenuItem, DropdownMenuSeparator)}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </ContextMenuTrigger>
                <ContextMenuContent>
                    {menu(ContextMenuItem as any, ContextMenuSeparator as any)}
                </ContextMenuContent>
            </ContextMenu>
            {expanded ? (
                <div>
                    {pager?.loading && pager.ids.length === 0 ? (
                        <Skeleton
                            className="my-1 h-5 w-1/2"
                            style={{marginLeft: (depth + 1) * 12 + 28}}
                        />
                    ) : null}
                    {pager?.ids.map(
                        id =>
                            collections[id] && (
                                <CollectionItem
                                    key={id}
                                    collection={collections[id]}
                                    depth={depth + 1}
                                />
                            )
                    )}
                    {pager?.next ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 text-xs"
                            style={{marginLeft: (depth + 1) * 12 + 28}}
                            onClick={() =>
                                loadMore(collection.workspaceId, collection.id)
                            }
                            loading={pager.loading}
                        >
                            {t('common.load_more', 'Load more')}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

export function collectionCondition(id: string): string {
    return `${BuiltInAttribute.Collection} = ${quoteAQL(id)}`;
}
