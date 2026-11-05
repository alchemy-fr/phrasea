'use client';

import {useTranslation} from 'react-i18next';
import type {TFunction} from 'i18next';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {
    FileInputIcon,
    PlugIcon,
    Trash2Icon,
    UploadIcon,
    UserIcon,
} from 'lucide-react';
import type {AssetImport, AssetImportStatus} from '@/types/api';
import type {WorkspaceTabProps} from '@/features/workspaces/manage/WorkspaceManageRoute';
import {
    deleteAssetImport,
    getAssetImportErrors,
    getAssetImports,
} from '@/lib/api/imports';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
    Badge,
    EmptyState,
    Progress,
    Skeleton,
} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {useConfirm} from '@/components/ui/confirm';
import {useModals} from '@/components/modals/ModalProvider';
import {LoadError} from '@/components/modals/LoadPlaceholder';
import {formatDateTime} from '@/lib/utils/format';
import {ImportAssetsDialog} from './ImportAssetsDialog';
import {assetImportErrorsQueryKey, assetImportsQueryKey} from './queryKeys';

const POLL_INTERVAL = 3000;

export function isImportRunning(status: AssetImportStatus): boolean {
    return (
        status === 'pending' ||
        status === 'dispatching' ||
        status === 'processing'
    );
}

function statusVariant(
    item: AssetImport
): 'success' | 'warning' | 'destructive' | 'secondary' {
    switch (item.status) {
        case 'completed':
            return item.failed > 0 ? 'warning' : 'success';
        case 'failed':
            return 'destructive';
        default:
            return 'secondary';
    }
}

export function importStatusLabel(
    status: AssetImportStatus,
    t: TFunction
): string {
    switch (status) {
        case 'pending':
            return t('import.status.pending', 'Pending');
        case 'dispatching':
            return t('import.status.dispatching', 'Reading the file');
        case 'processing':
            return t('import.status.processing', 'Processing');
        case 'completed':
            return t('import.status.completed', 'Completed');
        case 'failed':
            return t('import.status.failed', 'Failed');
    }
}

/** Share of the entries done, when their total is known */
export function importProgress(item: AssetImport): number | undefined {
    if (!item.total) {
        return undefined;
    }

    return Math.min(
        100,
        Math.round(
            (100 * (item.created + item.skipped + item.failed)) / item.total
        )
    );
}

/**
 * Imports of a workspace from instructions files, refreshed while one of
 * them is in progress.
 */
export function WorkspaceImportsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const imports = useInfiniteQuery({
        queryKey: assetImportsQueryKey(workspace.id),
        queryFn: ({pageParam}) => getAssetImports(workspace.id, pageParam),
        initialPageParam: 1,
        getNextPageParam: (last, pages) =>
            last.next ? pages.length + 1 : undefined,
        refetchInterval: query =>
            query.state.data?.pages.some(p =>
                p.items.some(i => isImportRunning(i.status))
            )
                ? POLL_INTERVAL
                : false,
    });
    const items = imports.data?.pages.flatMap(p => p.items) ?? [];

    const importButton = (
        <Button
            size="sm"
            onClick={() =>
                openModal(
                    ImportAssetsDialog,
                    {workspaceId: workspace.id},
                    {key: 'import-assets'}
                )
            }
        >
            <UploadIcon /> {t('import.new', 'Import assets…')}
        </Button>
    );

    if (imports.isLoading) {
        return (
            <div className="space-y-2">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
            </div>
        );
    }
    if (imports.error) {
        return <LoadError error={imports.error} className="" />;
    }

    if (items.length === 0) {
        return (
            <EmptyState
                icon={<FileInputIcon />}
                title={t('import.list.empty', 'No import yet')}
                description={t(
                    'import.list.empty_help',
                    'Create assets in bulk from a JSON or NDJSON file describing them.'
                )}
                action={importButton}
                testId="asset-imports-empty"
            />
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex justify-end">{importButton}</div>
            <Accordion type="multiple" data-testid="asset-imports">
                {items.map(item => (
                    <ImportRow
                        key={item.id}
                        item={item}
                        workspaceId={workspace.id}
                    />
                ))}
            </Accordion>
            {imports.hasNextPage ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-full"
                    onClick={() => imports.fetchNextPage()}
                    loading={imports.isFetchingNextPage}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </div>
    );
}

function ImportRow({
    item,
    workspaceId,
}: {
    item: AssetImport;
    workspaceId: string;
}) {
    const {t, i18n} = useTranslation();
    const confirm = useConfirm();
    const queryClient = useQueryClient();
    const progress = importProgress(item);
    const running = isImportRunning(item.status);

    const remove = () =>
        void confirm({
            title: t('import.delete.title', 'Delete this import?'),
            description: t(
                'import.delete.description',
                'Its tracking and its file are deleted. The assets it created are kept.'
            ),
            confirmLabel: t('common.delete', 'Delete'),
            destructive: true,
            onConfirm: () => deleteAssetImport(item.id),
            onConfirmed: () =>
                void queryClient.invalidateQueries({
                    queryKey: assetImportsQueryKey(workspaceId),
                }),
        });

    return (
        <AccordionItem value={item.id} data-testid="asset-import">
            <AccordionTrigger
                actions={
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        className="text-muted-foreground"
                        aria-label={t('common.delete', 'Delete')}
                        title={t('common.delete', 'Delete')}
                        onClick={remove}
                    >
                        <Trash2Icon />
                    </Button>
                }
            >
                <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <span className="flex min-w-0 flex-wrap items-center gap-2">
                        <Badge variant={statusVariant(item)}>
                            {importStatusLabel(item.status, t)}
                        </Badge>
                        <span className="min-w-0 truncate">
                            {item.originalName ??
                                t('import.untitled', 'Untitled file')}
                        </span>
                        <span className="text-muted-foreground">
                            {formatDateTime(
                                item.createdAt,
                                'medium',
                                i18n.language
                            )}
                        </span>
                        <ImportAuthor item={item} />
                        <span
                            className="ml-auto text-xs font-normal text-muted-foreground"
                            data-testid="asset-import-counts"
                        >
                            {t(
                                'import.counts',
                                '{{created}} created · {{skipped}} skipped · {{failed}} failed · {{total}} total',
                                {
                                    created: item.created,
                                    skipped: item.skipped,
                                    failed: item.failed,
                                    total: item.total ?? '?',
                                }
                            )}
                        </span>
                    </span>
                    {progress !== undefined && running ? (
                        <Progress value={progress} />
                    ) : running ? (
                        <Progress indeterminate />
                    ) : null}
                </span>
            </AccordionTrigger>
            <AccordionContent>
                <ImportDetails item={item} />
            </AccordionContent>
        </AccordionItem>
    );
}

function ImportAuthor({item}: {item: AssetImport}) {
    if (item.integrationTitle) {
        return (
            <span className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                <PlugIcon className="size-3" /> {item.integrationTitle}
            </span>
        );
    }
    if (item.owner) {
        return (
            <span className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                <UserIcon className="size-3" /> {item.owner.username}
            </span>
        );
    }

    return null;
}

function ImportDetails({item}: {item: AssetImport}) {
    const {t, i18n} = useTranslation();

    return (
        <div className="space-y-3">
            <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-xs">
                <dt className="text-muted-foreground">
                    {t('import.destination', 'Destination')}
                </dt>
                <dd>
                    {item.collectionTitle ??
                        (item.collectionId
                            ? item.collectionId
                            : t('import.workspace_root', 'Workspace root'))}
                </dd>
                <dt className="text-muted-foreground">
                    {t('import.format', 'Format')}
                </dt>
                <dd>{item.format.toUpperCase()}</dd>
                <dt className="text-muted-foreground">
                    {t('import.updated_at', 'Updated at')}
                </dt>
                <dd>
                    {formatDateTime(item.updatedAt, 'medium', i18n.language)}
                </dd>
                <dt className="text-muted-foreground">
                    {t('import.ended_at', 'Ended at')}
                </dt>
                <dd>
                    {formatDateTime(item.endedAt, 'medium', i18n.language) ||
                        '—'}
                </dd>
            </dl>
            {item.error ? (
                <p
                    className="rounded-md bg-destructive/10 p-2 text-xs text-destructive"
                    data-testid="asset-import-error"
                >
                    {item.error}
                </p>
            ) : null}
            {item.failed > 0 ? (
                <ImportErrors
                    importId={item.id}
                    running={isImportRunning(item.status)}
                />
            ) : null}
        </div>
    );
}

function ImportErrors({
    importId,
    running,
}: {
    importId: string;
    running: boolean;
}) {
    const {t} = useTranslation();
    const errors = useInfiniteQuery({
        queryKey: assetImportErrorsQueryKey(importId),
        queryFn: ({pageParam}) => getAssetImportErrors(importId, pageParam),
        initialPageParam: 1,
        getNextPageParam: (last, pages) =>
            last.next ? pages.length + 1 : undefined,
        refetchInterval: running ? POLL_INTERVAL : false,
    });
    const items = errors.data?.pages.flatMap(p => p.items) ?? [];
    const total = errors.data?.pages[0]?.total ?? 0;

    if (errors.isLoading) {
        return <Skeleton className="h-16 w-full" />;
    }
    if (errors.error) {
        return <LoadError error={errors.error} className="p-2" />;
    }

    return (
        <div className="space-y-1">
            <p className="text-xs font-medium">
                {t('import.errors.title', '{{count}} rejected entries', {
                    count: total,
                })}
            </p>
            <div className="max-h-72 overflow-y-auto rounded-md border">
                <table
                    className="w-full text-xs"
                    data-testid="asset-import-errors"
                >
                    <thead className="sticky top-0 bg-muted text-left text-muted-foreground">
                        <tr>
                            <th className="px-2 py-1 font-medium">
                                {t('import.errors.line', 'Line')}
                            </th>
                            <th className="px-2 py-1 font-medium">
                                {t('import.errors.key', 'Key')}
                            </th>
                            <th className="px-2 py-1 font-medium">
                                {t('import.errors.message', 'Error')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map(e => (
                            <tr key={e.id} className="border-t align-top">
                                <td className="px-2 py-1 font-mono">
                                    {e.line}
                                </td>
                                <td className="px-2 py-1 font-mono break-all">
                                    {e.key ?? '—'}
                                </td>
                                <td className="px-2 py-1">{e.message}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {errors.hasNextPage ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-full"
                    onClick={() => errors.fetchNextPage()}
                    loading={errors.isFetchingNextPage}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </div>
    );
}
