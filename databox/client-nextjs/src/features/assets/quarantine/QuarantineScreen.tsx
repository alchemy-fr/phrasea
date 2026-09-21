'use client';

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import Link from 'next/link';
import {useSearchParams} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery, useQuery} from '@tanstack/react-query';
import {
    ChevronRightIcon,
    ExternalLinkIcon,
    RefreshCwIcon,
    SearchIcon,
    ShieldAlertIcon,
    ShieldCheckIcon,
} from 'lucide-react';
import type {Asset, DuplicateAsset} from '@/types/api';
import {getAsset, getAssetDuplicates, searchAssets} from '@/lib/api/assets';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {Button} from '@/components/ui/button';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {InlineLoader} from '@/components/ui/loader';
import {CollectionChip, FileTypeChip, WorkspaceChip} from '@/components/chips';
import {routes} from '@/lib/routes';
import {formatDateTime, formatFileSize} from '@/lib/utils/format';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {
    BuiltInAttribute,
    emptySearchState,
    searchStateToParams,
} from '@/features/search/searchState';
import {AnalysisReport} from './AnalysisReport';
import {DuplicatesList} from './DuplicatesList';
import {QuarantineActions} from './QuarantineActions';
import {fileAnalysis, hasAnalysisReport, quarantineCondition} from './analysis';
import {quarantineQueueKey, useQuarantineQueueStore} from './quarantineQueue';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {cn} from '@/lib/utils/cn';

const pageSize = 30;

/** Same results as the search screen filtered on `Asset status = Quarantined`. */
const searchScreenUrl = `${routes.assets()}?${searchStateToParams({
    ...emptySearchState,
    conditions: [quarantineCondition],
}).toString()}`;

/**
 * Dedicated screen to work through the quarantine queue: the oldest
 * quarantined assets on the left, the one being reviewed on the right with its
 * analysis report, its duplicates and the actions that take it out of
 * quarantine. Resolving an asset moves on to the next one.
 */
export function QuarantineScreen() {
    const {t, i18n} = useTranslation();
    const searchParams = useSearchParams();
    const linkedId = searchParams.get('asset') ?? undefined;
    const [selectedId, setSelectedId] = useState<string | undefined>(linkedId);
    const resolved = useQuarantineQueueStore(s => s.resolved);
    const resetResolved = useQuarantineQueueStore(s => s.reset);

    const queue = useInfiniteQuery({
        queryKey: quarantineQueueKey,
        queryFn: ({pageParam}) =>
            searchAssets({
                url: pageParam,
                conditions: [quarantineCondition.query],
                order: {[BuiltInAttribute.CreatedAt]: 'asc'},
                limit: pageSize,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        // Coming back to the screen must show what is in quarantine now, not
        // the queue as it was when it was left.
        refetchOnMount: 'always',
        // An asset only lands in quarantine once the analysis, then the
        // indexing, are through: nothing tells the client exactly when.
        refetchInterval: 60000,
    });

    // Assets being ingested announce their renditions on this channel: it is
    // the signal that new ones may have landed in quarantine. Bulk uploads
    // emit one event per rendition, hence the trailing debounce.
    const refresh = useRef<ReturnType<typeof setTimeout>>(undefined);
    useChannelEvent('assets', 'rendition-update', () => {
        clearTimeout(refresh.current);
        refresh.current = setTimeout(() => void queue.refetch(), 2000);
    });
    useEffect(() => () => clearTimeout(refresh.current), []);

    const loaded = useMemo(
        () => queue.data?.pages.flatMap(p => p.items) ?? [],
        [queue.data]
    );
    const assets = useMemo(
        () => loaded.filter(a => !resolved.includes(a.id)),
        [loaded, resolved]
    );
    // Only the resolved assets the server still returns have to be discounted
    // from its total.
    const total = Math.max(
        (queue.data?.pages[0]?.total ?? 0) - (loaded.length - assets.length),
        0
    );

    const select = useCallback((id: string | undefined) => {
        setSelectedId(id);
        // Shallow: the queue and the current asset are local state, only the
        // URL has to stay shareable.
        window.history.replaceState(
            window.history.state,
            '',
            routes.quarantine(id)
        );
    }, []);

    useEffect(() => {
        if (linkedId) {
            setSelectedId(linkedId);
        }
    }, [linkedId]);

    // Nothing selected (first load, or the selected asset just left the
    // queue): take the head of the queue.
    useEffect(() => {
        if (!selectedId && assets.length > 0) {
            select(assets[0].id);
        }
    }, [assets, selectedId, select]);

    const index = assets.findIndex(a => a.id === selectedId);
    const listed = index >= 0 ? assets[index] : undefined;

    // A link from a banner may point at an asset that is not in the loaded
    // pages (deep link, or a page not reached yet).
    const linked = useQuery({
        queryKey: ['asset', selectedId],
        queryFn: () => getAsset(selectedId!),
        enabled: !!selectedId && !listed,
        staleTime: 2000,
    });
    const asset =
        listed ?? (linked.data?.id === selectedId ? linked.data : undefined);

    const duplicates = useQuery({
        queryKey: ['asset-duplicates', asset?.id],
        queryFn: () => getAssetDuplicates(asset!.id),
        enabled: !!asset,
    });

    const goTo = (offset: number) => {
        const next = assets[index + offset];
        if (next) {
            select(next.id);
        }
    };

    // The resolution itself (store + cache invalidation) is handled by
    // `QuarantineActions`: the queue only has to move on.
    const onResolved = () => {
        const next =
            index >= 0 ? (assets[index + 1] ?? assets[index - 1]) : undefined;
        select(next?.id);
    };

    return (
        <RequireAuth>
            <div className="flex h-full min-h-0 flex-col">
                <header className="flex shrink-0 items-center gap-2 border-b px-3 py-2">
                    <ShieldAlertIcon className="size-5 text-destructive" />
                    <h1 className="font-semibold">
                        {t('quarantine.queue.title', 'Quarantine')}
                    </h1>
                    {queue.isSuccess ? (
                        <Badge variant="muted" data-testid="quarantine-count">
                            {t('quarantine.queue.count', {
                                count: total,
                                defaultValue_one: '{{count}} asset to review',
                                defaultValue_other:
                                    '{{count}} assets to review',
                            })}
                        </Badge>
                    ) : null}
                    <div className="flex-1" />
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            resetResolved();
                            void queue.refetch();
                        }}
                        loading={queue.isRefetching}
                    >
                        <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <Link href={searchScreenUrl}>
                            <SearchIcon />{' '}
                            {t(
                                'quarantine.queue.open_in_search',
                                'Open in search'
                            )}
                        </Link>
                    </Button>
                </header>

                <div className="flex min-h-0 flex-1">
                    <aside
                        className="flex w-[280px] shrink-0 flex-col overflow-y-auto border-r"
                        data-testid="quarantine-queue"
                    >
                        {queue.isLoading ? (
                            <div className="space-y-2 p-2">
                                {[...Array(5)].map((_, i) => (
                                    <Skeleton key={i} className="h-12" />
                                ))}
                            </div>
                        ) : null}
                        <ul>
                            {assets.map(a => (
                                <li key={a.id}>
                                    <button
                                        type="button"
                                        data-testid="quarantine-queue-item"
                                        className={cn(
                                            'flex w-full items-center gap-2 border-b px-2 py-1.5 text-left hover:bg-accent',
                                            a.id === selectedId &&
                                                'bg-primary/10'
                                        )}
                                        onClick={() => select(a.id)}
                                    >
                                        <span className="size-10 shrink-0 overflow-hidden rounded bg-media-bg">
                                            <AssetThumb
                                                asset={a}
                                                size={40}
                                                ignoreAnalysis
                                            />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm">
                                                {a.name ?? a.source?.fileName}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {formatDateTime(
                                                    a.createdAt,
                                                    'short',
                                                    i18n.language
                                                )}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                        {queue.hasNextPage ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="m-2"
                                loading={queue.isFetchingNextPage}
                                onClick={() => void queue.fetchNextPage()}
                            >
                                {t('common.load_more', 'Load more')}
                            </Button>
                        ) : null}
                    </aside>

                    <div className="min-w-0 flex-1 overflow-y-auto">
                        {asset ? (
                            <QuarantineDetails
                                asset={asset}
                                duplicates={duplicates.data}
                                position={
                                    index >= 0
                                        ? {
                                              current: index + 1,
                                              hasNext:
                                                  index < assets.length - 1,
                                          }
                                        : undefined
                                }
                                onSkip={() => goTo(1)}
                                onResolved={onResolved}
                            />
                        ) : linked.isLoading ? (
                            <InlineLoader className="m-4" />
                        ) : queue.isLoading ? null : assets.length === 0 ? (
                            <EmptyState
                                className="h-full"
                                testId="quarantine-empty"
                                icon={<ShieldCheckIcon />}
                                title={t(
                                    'quarantine.queue.empty',
                                    'Nothing in quarantine'
                                )}
                                description={t(
                                    'quarantine.queue.empty_help',
                                    'Assets rejected by the analyzers show up here, waiting to be accepted, merged or deleted.'
                                )}
                            />
                        ) : (
                            <EmptyState
                                className="h-full"
                                title={t(
                                    'quarantine.queue.select',
                                    'Select an asset to resolve'
                                )}
                            />
                        )}
                    </div>
                </div>
            </div>
        </RequireAuth>
    );
}

function QuarantineDetails({
    asset,
    duplicates,
    position,
    onSkip,
    onResolved,
}: {
    asset: Asset;
    duplicates?: DuplicateAsset[];
    position?: {current: number; hasNext: boolean};
    onSkip: () => void;
    onResolved: () => void;
}) {
    const {t, i18n} = useTranslation();
    const source = asset.source;
    const previewFile = asset.preview?.file?.url
        ? asset.preview.file
        : asset.thumbnail?.file?.url
          ? asset.thumbnail.file
          : undefined;

    return (
        <div className="space-y-4 p-4" data-testid="quarantine-details">
            <div className="flex items-start gap-2">
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-lg font-semibold">
                        {asset.name ?? source?.fileName ?? asset.id}
                    </h2>
                    <div className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                        <WorkspaceChip workspace={asset.workspace} />
                        {asset.collections?.slice(0, 3).map(c => (
                            <CollectionChip key={c.id} collection={c} />
                        ))}
                        {source ? (
                            <FileTypeChip
                                mimeType={source.type}
                                extension={source.extension}
                            />
                        ) : null}
                        {source?.size ? (
                            <span>
                                {formatFileSize(
                                    source.size,
                                    true,
                                    i18n.language
                                )}
                            </span>
                        ) : null}
                        <span>
                            {formatDateTime(
                                asset.createdAt,
                                'short',
                                i18n.language
                            )}
                        </span>
                    </div>
                </div>
                {position ? (
                    <Badge variant="muted">#{position.current}</Badge>
                ) : null}
                <Button size="sm" variant="ghost" asChild>
                    <Link href={routes.assetView(asset.id)}>
                        <ExternalLinkIcon />{' '}
                        {t('quarantine.open_asset', 'Open asset')}
                    </Link>
                </Button>
                {position?.hasNext ? (
                    <Button
                        size="sm"
                        variant="ghost"
                        data-testid="quarantine-skip"
                        onClick={onSkip}
                    >
                        {t('quarantine.queue.skip', 'Skip')}{' '}
                        <ChevronRightIcon />
                    </Button>
                ) : null}
            </div>

            <div className="flex h-[320px] items-center justify-center overflow-hidden rounded-lg border bg-media-bg">
                {previewFile ? (
                    <FilePlayer
                        file={previewFile}
                        title={asset.name}
                        fit="contain"
                        className="size-full"
                    />
                ) : (
                    <AssetThumb asset={asset} ignoreAnalysis />
                )}
            </div>

            {hasAnalysisReport(asset) ? (
                <section>
                    <p className="mb-1 text-xs font-semibold text-muted-foreground uppercase">
                        {t('quarantine.analysis', 'Analysis report')}
                    </p>
                    <AnalysisReport
                        analysis={fileAnalysis(source)}
                        className="rounded-lg border p-2"
                    />
                </section>
            ) : null}

            {duplicates && duplicates.length > 0 ? (
                <DuplicatesList duplicates={duplicates} />
            ) : null}

            <QuarantineActions
                asset={asset}
                duplicates={duplicates}
                onResolved={onResolved}
            />
        </div>
    );
}
