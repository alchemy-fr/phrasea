'use client';

import {useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import type {
    ColumnFiltersState,
    RowSelectionState,
    SortingState,
} from '@tanstack/react-table';
import {
    ArrowLeftIcon,
    DownloadIcon,
    EraserIcon,
    ListPlusIcon,
    MergeIcon,
    MoreVerticalIcon,
    PlusIcon,
    ThumbsDownIcon,
    ThumbsUpIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeEntity, EntityList, Workspace} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    type AttributeEntitySortField,
    clearEntityList,
    deleteAttributeEntity,
    getAttributeEntity,
    postAttributeEntity,
    putAttributeEntity,
} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {FilterInput} from '@/components/ui/filter-input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {ConfirmDialog} from '@/components/ui/confirm';
import {useModals} from '@/components/modals/ModalProvider';
import {DataTable} from '@/components/ui/data-table/DataTable';
import type {CellPosition} from '@/components/ui/data-table/types';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {useRoutePath} from '@/lib/navigation/routePath';
import {toastError} from '@/lib/utils/errors';
import {cn} from '@/lib/utils/cn';
import {
    buildEntityColumns,
    DRAFT_PREFIX,
    entityPatchFromCell,
    isDraft,
} from './entityColumns';
import {EntityCellEditor} from './EntityCellEditor';
import {EntityFormDialog} from './EntityFormDialog';
import {ExploreEntityListButton} from '@/features/entities/explore/ExploreEntityListButton';
import {EntityAssetsPreviewDialog} from './EntityAssetsPreviewDialog';
import {
    ExportEntitiesDialog,
    ImportEntitiesDialog,
    MergeEntitiesDialog,
} from './dialogs';
import {
    type EntityStatusFilter,
    pendingCountQueryKey,
    useEntityGridQuery,
    usePendingCount,
} from './useEntityGridQuery';

const SORTABLE: AttributeEntitySortField[] = [
    'value',
    'status',
    'note',
    'createdAt',
    'updatedAt',
];
/** Parallel requests of a bulk status change */
const BATCH_SIZE = 8;

async function inBatches<T>(
    items: T[],
    fn: (item: T) => Promise<unknown>
): Promise<PromiseSettledResult<unknown>[]> {
    const results: PromiseSettledResult<unknown>[] = [];
    for (let i = 0; i < items.length; i += BATCH_SIZE) {
        results.push(
            ...(await Promise.allSettled(
                items.slice(i, i + BATCH_SIZE).map(fn)
            ))
        );
    }

    return results;
}

/**
 * The values of an entity list as a spreadsheet: inline edition, moderation
 * (status chips, approve / reject, one by one or the selection), server-side
 * sort and filters, infinite loading.
 *
 * The value edited in the full form is in the URL (`useRoutePath`):
 * `:entityId` or `new`.
 */
export function EntityGrid({
    list,
    workspace,
    onBack,
}: {
    list: EntityList;
    workspace: Workspace;
    onBack: () => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const locales = useMemo(
        () => workspace.enabledLocales ?? [],
        [workspace.enabledLocales]
    );

    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search.trim(), 250);
    const [statusFilter, setStatusFilter] = useState<EntityStatusFilter>('all');
    const [sorting, setSorting] = useState<SortingState>([
        {id: 'value', desc: false},
    ]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const debouncedColumnFilters = useDebouncedValue(columnFilters, 250);
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [drafts, setDrafts] = useState<AttributeEntity[]>([]);
    const draftSeq = useRef(0);
    const [requestFocus, setRequestFocus] = useState<
        (CellPosition & {edit?: boolean}) | null
    >(null);

    const columnFilter = (id: string): string | undefined => {
        const v = debouncedColumnFilters.find(f => f.id === id)?.value;

        return typeof v === 'string' && v.trim() ? v.trim() : undefined;
    };
    const sortId = sorting[0]?.id as AttributeEntitySortField | undefined;
    const grid = useEntityGridQuery({
        listId: list.id,
        sort: {
            id: sortId && SORTABLE.includes(sortId) ? sortId : 'value',
            desc: sorting[0]?.desc ?? false,
        },
        filters: {
            search: debouncedSearch,
            status: statusFilter,
            value: debouncedSearch ? undefined : columnFilter('value'),
            note: columnFilter('note'),
        },
    });
    const pending = usePendingCount(list.id);
    const refreshPending = () =>
        void queryClient.invalidateQueries({
            queryKey: pendingCountQueryKey(list.id),
        });
    const data = useMemo(
        () => [...drafts, ...grid.items],
        [drafts, grid.items]
    );

    const selectedIds = Object.keys(rowSelection).filter(
        id => rowSelection[id] && !id.startsWith(DRAFT_PREFIX)
    );
    const selected = grid.items.filter(e => selectedIds.includes(e.id));

    const {segments, navigate} = useRoutePath();
    const editingId = segments[0] ?? null;
    const listed = grid.items.find(e => e.id === editingId);
    const single = useQuery({
        queryKey: ['attribute-entity', editingId],
        queryFn: () => getAttributeEntity(editingId!),
        enabled:
            !!editingId && editingId !== 'new' && !listed && !grid.isLoading,
        retry: false,
    });
    const editing = editingId === 'new' ? undefined : (listed ?? single.data);

    const discardDraft = (id: string) =>
        setDrafts(ds => ds.filter(d => d.id !== id));

    const addRow = () => {
        const draft: AttributeEntity = {
            id: `${DRAFT_PREFIX}${++draftSeq.current}`,
            value: '',
            translations: {},
            createdAt: '',
            updatedAt: '',
            status: AttributeEntityStatus.Approved,
        } as unknown as AttributeEntity;
        setDrafts(ds => [draft, ...ds]);
        setRequestFocus({rowIndex: 0, columnId: 'value', edit: true});
    };

    const commitCell = async (
        row: AttributeEntity,
        columnId: string,
        value: string
    ) => {
        if (isDraft(row)) {
            if (columnId !== 'value') {
                return;
            }
            const v = value.trim();
            if (!v) {
                discardDraft(row.id);

                return;
            }
            try {
                // Created by a list editor: approved, as with the form
                const saved = await postAttributeEntity(list.id, {
                    value: v,
                    status: AttributeEntityStatus.Approved,
                });
                discardDraft(row.id);
                grid.insertEntity(saved);
                setRequestFocus({
                    rowIndex: drafts.filter(d => d.id !== row.id).length,
                    columnId: 'value',
                });
                refreshPending();
            } catch (e) {
                toastError(e);
            }

            return;
        }

        const patch = entityPatchFromCell(row, columnId, value);
        if (!patch) {
            toast.error(t('entity.value_required', 'A value is required'));

            return;
        }
        const previous = Object.fromEntries(
            Object.keys(patch).map(k => [k, row[k as keyof AttributeEntity]])
        ) as Partial<AttributeEntity>;
        grid.patchEntity(row.id, patch);
        try {
            const saved = await putAttributeEntity(row.id, patch);
            grid.replaceEntity(saved);
            if ('status' in patch) {
                refreshPending();
            }
        } catch (e) {
            grid.patchEntity(row.id, previous);
            toastError(e);
        }
    };

    const setStatus = async (
        entities: AttributeEntity[],
        status: AttributeEntityStatus
    ) => {
        const changed = entities.filter(e => e.status !== status);
        if (changed.length === 0) {
            return;
        }
        changed.forEach(e => grid.patchEntity(e.id, {status}));
        const results = await inBatches(changed, e =>
            putAttributeEntity(e.id, {status})
        );
        let done = 0;
        results.forEach((r, i) => {
            const e = changed[i];
            if (r.status === 'fulfilled') {
                done++;
                grid.replaceEntity(r.value as AttributeEntity);
            } else {
                grid.patchEntity(e.id, {status: e.status});
            }
        });
        const failed = results.find(
            (r): r is PromiseRejectedResult => r.status === 'rejected'
        );
        if (failed) {
            toastError(failed.reason);
        }
        if (done > 0 && changed.length > 1) {
            toast.success(
                status === AttributeEntityStatus.Approved
                    ? t('entity.approved_count', '{{count}} values approved', {
                          count: done,
                      })
                    : t('entity.rejected_count', '{{count}} values rejected', {
                          count: done,
                      })
            );
        }
        refreshPending();
        setRowSelection({});
    };

    const confirmDelete = (entities: AttributeEntity[]) =>
        openModal(ConfirmDialog, {
            title: t('entity.delete_selected', 'Delete {{count}} values?', {
                count: entities.length,
            }),
            destructive: true,
            onConfirm: async () => {
                const results = await Promise.allSettled(
                    entities.map(e => deleteAttributeEntity(e.id))
                );
                const deleted = entities
                    .filter((_, i) => results[i].status === 'fulfilled')
                    .map(e => e.id);
                grid.removeEntities(deleted);
                setRowSelection({});
                if (editingId && deleted.includes(editingId)) {
                    navigate([], {replace: true});
                }
                const failed = results.find(
                    (r): r is PromiseRejectedResult => r.status === 'rejected'
                );
                if (failed) {
                    toastError(failed.reason);
                }
                if (deleted.length > 0) {
                    toast.success(
                        t('entity.deleted_count', '{{count}} values deleted', {
                            count: deleted.length,
                        })
                    );
                }
                refreshPending();
            },
        });

    const reload = () => {
        void grid.refetch();
        refreshPending();
    };

    // The columns are memoized: their callbacks go through the latest handlers
    const actions = useRef({
        approve: (_e: AttributeEntity) => {},
        reject: (_e: AttributeEntity) => {},
        remove: (_e: AttributeEntity) => {},
    });
    actions.current = {
        approve: e => void setStatus([e], AttributeEntityStatus.Approved),
        reject: e => void setStatus([e], AttributeEntityStatus.Rejected),
        remove: e => confirmDelete([e]),
    };
    const columns = useMemo(
        () =>
            buildEntityColumns({
                list,
                locales,
                t,
                onApprove: e => actions.current.approve(e),
                onReject: e => actions.current.reject(e),
                onPreview: e =>
                    openModal(EntityAssetsPreviewDialog, {
                        entity: e,
                        list,
                        workspaceId: workspace.id,
                    }),
                onEdit: e => navigate([e.id]),
                onDelete: e => actions.current.remove(e),
                onDiscardDraft: e =>
                    setDrafts(ds => ds.filter(d => d.id !== e.id)),
            }),
        [list, locales, t, workspace.id, openModal, navigate]
    );

    const chips: {id: EntityStatusFilter; label: string}[] = [
        {id: 'all', label: t('entity.filter.all', 'All')},
        {id: 'pending', label: t('entity.pending', 'Pending')},
        {id: 'approved', label: t('entity.approved', 'Approved')},
        {id: 'rejected', label: t('entity.rejected', 'Rejected')},
    ];

    return (
        <div className="flex h-full min-h-0 flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <Button variant="ghost" size="sm" onClick={onBack}>
                    <ArrowLeftIcon /> {t('common.back', 'Back')}
                </Button>
                <h3 className="text-sm font-semibold">{list.name}</h3>
                <ExploreEntityListButton listId={list.id} />
                <FilterInput
                    value={search}
                    onValueChange={setSearch}
                    placeholder={t('common.search', 'Search…')}
                    className="w-56"
                    data-testid="entity-search"
                />
                <div className="flex items-center gap-1" role="group">
                    {chips.map(c => (
                        <Button
                            key={c.id}
                            size="sm"
                            variant={
                                statusFilter === c.id ? 'secondary' : 'ghost'
                            }
                            aria-pressed={statusFilter === c.id}
                            data-testid={`entity-filter-${c.id}`}
                            onClick={() => {
                                setStatusFilter(c.id);
                                setRowSelection({});
                            }}
                        >
                            {c.label}
                            {c.id === 'pending' && pending.data ? (
                                <Badge
                                    variant="warning"
                                    data-testid="entity-pending-count"
                                    title={t(
                                        'entity.pending_count',
                                        '{{count}} pending',
                                        {count: pending.data}
                                    )}
                                >
                                    {pending.data}
                                </Badge>
                            ) : null}
                        </Button>
                    ))}
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => navigate(['new'])}
                    data-testid="entity-create"
                >
                    <PlusIcon /> {t('entity.create', 'New value')}
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={addRow}
                    data-testid="entity-add-row"
                >
                    <ListPlusIcon /> {t('entity.add_row', 'Add row')}
                </Button>
                {selected.length > 0 ? (
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                void setStatus(
                                    selected,
                                    AttributeEntityStatus.Approved
                                )
                            }
                            data-testid="entity-approve-selected"
                        >
                            <ThumbsUpIcon />{' '}
                            {t(
                                'entity.approve_selected',
                                'Approve {{count}} values',
                                {
                                    count: selected.length,
                                }
                            )}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                void setStatus(
                                    selected,
                                    AttributeEntityStatus.Rejected
                                )
                            }
                            data-testid="entity-reject-selected"
                        >
                            <ThumbsDownIcon />{' '}
                            {t(
                                'entity.reject_selected',
                                'Reject {{count}} values',
                                {
                                    count: selected.length,
                                }
                            )}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={selected.length < 2}
                            onClick={() =>
                                openModal(MergeEntitiesDialog, {
                                    entities: selected,
                                    onMerged: () => {
                                        setRowSelection({});
                                        reload();
                                    },
                                })
                            }
                        >
                            <MergeIcon /> {t('entity.merge', 'Merge')}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => confirmDelete(selected)}
                        >
                            <Trash2Icon /> {t('common.delete', 'Delete')}
                        </Button>
                    </>
                ) : null}
                <span className="ml-auto text-xs text-muted-foreground">
                    {grid.total !== undefined
                        ? t('entity.count', '{{count}} values', {
                              count: grid.total,
                          })
                        : null}
                </span>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('common.more', 'More')}
                        >
                            <MoreVerticalIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            onSelect={() =>
                                openModal(ExportEntitiesDialog, {
                                    list,
                                    locales,
                                })
                            }
                        >
                            <DownloadIcon /> {t('entity.export', 'Export…')}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() =>
                                openModal(ImportEntitiesDialog, {
                                    list,
                                    onImported: reload,
                                })
                            }
                        >
                            <UploadIcon /> {t('entity.import', 'Import…')}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() =>
                                openModal(ConfirmDialog, {
                                    title: t(
                                        'entity.clear.title',
                                        'Remove all values of "{{name}}"?',
                                        {name: list.name}
                                    ),
                                    destructive: true,
                                    textToType: list.name,
                                    onConfirm: async () => {
                                        await clearEntityList(list.id);
                                        setRowSelection({});
                                        reload();
                                    },
                                })
                            }
                        >
                            <EraserIcon /> {t('entity.clear', 'Clear list')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <DataTable<AttributeEntity>
                data={data}
                columns={columns}
                getRowId={e => e.id}
                sorting={sorting}
                onSortingChange={setSorting}
                columnFilters={columnFilters}
                onColumnFiltersChange={setColumnFilters}
                rowSelection={rowSelection}
                onRowSelectionChange={setRowSelection}
                onCellCommit={commitCell}
                renderEditor={p => <EntityCellEditor {...p} />}
                rowClassName={e =>
                    cn(
                        e.status === AttributeEntityStatus.Pending &&
                            !isDraft(e) &&
                            'bg-warning/10',
                        e.status === AttributeEntityStatus.Rejected &&
                            'opacity-60'
                    ) || undefined
                }
                loading={grid.isLoading}
                loadingMore={grid.isFetchingNextPage}
                hasMore={grid.hasNextPage}
                onReachEnd={() => void grid.fetchNextPage()}
                emptyLabel={t('entity.empty', 'No value')}
                requestFocus={requestFocus}
                data-testid="entity-grid"
                className="min-h-0 flex-1"
            />
            <EntityFormDialog
                open={!!editingId}
                onOpenChange={open => !open && navigate([])}
                entity={editing}
                loading={!!editingId && editingId !== 'new' && !editing}
                notFound={single.isError}
                list={list}
                locales={locales}
                onSaved={saved => {
                    if (editing) {
                        grid.replaceEntity(saved);
                    } else {
                        grid.insertEntity(saved);
                    }
                    refreshPending();
                    navigate([]);
                }}
            />
        </div>
    );
}
