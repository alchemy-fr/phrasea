'use client';

import {useCallback, useMemo, useRef, useState} from 'react';
import Link from 'next/link';
import {useSearchParams} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {
    useInfiniteQuery,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {CompassIcon, ListIcon, RefreshCwIcon} from 'lucide-react';
import {AttributeEntityStatus} from '@/types/api';
import {getAttributeEntities, getEntityList} from '@/lib/api/metadata';
import {getWorkspace} from '@/lib/api/collections';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {routes} from '@/lib/routes';
import {idFromIri} from '@/lib/utils/iri';
import {Button} from '@/components/ui/button';
import {FilterInput} from '@/components/ui/filter-input';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {usePageTrail} from '@/components/layout/layoutStore';
import {useDebouncedValue} from '@/hooks/useDebouncedValue';
import {useInfiniteScroll} from '@/hooks/useInfiniteScroll';
import {
    useEntityListDefinitions,
    useWorkspaceDefinitions,
} from '@/features/workspaces/manage/entities/useEntityAssets';
import {EntityValueCard} from './EntityValueCard';

const pageSize = 24;

export const entityListExploreQueryKey = 'entity-list-explore';

export type StatusFilter = 'approved' | 'pending' | 'all';

const statuses: Record<
    StatusFilter,
    AttributeEntityStatus | AttributeEntityStatus[]
> = {
    approved: AttributeEntityStatus.Approved,
    pending: AttributeEntityStatus.Pending,
    // The rejected values are no values of the list anymore
    all: [AttributeEntityStatus.Approved, AttributeEntityStatus.Pending],
};

type Filters = {
    value: string;
    status: StatusFilter;
};

function filtersFromParams(params: URLSearchParams): Filters {
    const status = params.get('status');

    return {
        value: params.get('q') ?? '',
        status: status === 'pending' || status === 'all' ? status : 'approved',
    };
}

function filtersToParams(filters: Filters): URLSearchParams {
    const params = new URLSearchParams();
    if (filters.value) {
        params.set('q', filters.value);
    }
    if (filters.status !== 'approved') {
        params.set('status', filters.status);
    }

    return params;
}

/**
 * Full-page exploration of the values of an entity list: each value with a
 * few of the assets holding it and the way to their search; the list editors
 * rename the values or merge them.
 */
export function EntityListExploreScreen({listId}: {listId: string}) {
    return (
        <RequireAuth>
            <ExploreBrowser listId={listId} />
        </RequireAuth>
    );
}

function ExploreBrowser({listId}: {listId: string}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const searchParams = useSearchParams();
    const [filters, setFilters] = useState<Filters>(() =>
        filtersFromParams(searchParams)
    );
    const debouncedValue = useDebouncedValue(filters.value.trim(), 250);
    const scrollRef = useRef<HTMLDivElement>(null);

    const list = useQuery({
        queryKey: ['entity-list', listId],
        queryFn: () => getEntityList(listId),
    });
    const workspaceId =
        typeof list.data?.workspace === 'string'
            ? idFromIri(list.data.workspace)
            : list.data?.workspace?.id;
    const workspace = useQuery({
        queryKey: ['workspace', workspaceId],
        queryFn: () => getWorkspace(workspaceId!),
        enabled: !!workspaceId,
    });
    const canEdit = !!workspace.data?.capabilities?.edit;

    usePageTrail(
        list.data?.name ?? t('entity_list.explore.title', 'Explore values'),
        routes.entityListExplore(listId)
    );

    const definitionsLoaded = useWorkspaceDefinitions(workspaceId);
    const listDefinitions = useEntityListDefinitions(workspaceId, listId);

    // Shallow: only the URL has to stay shareable
    const update = useCallback(
        (patch: Partial<Filters>) => {
            setFilters(prev => {
                const next = {...prev, ...patch};
                const params = filtersToParams(next).toString();
                window.history.replaceState(
                    window.history.state,
                    '',
                    `${routes.entityListExplore(listId)}${params ? `?${params}` : ''}`
                );

                return next;
            });
        },
        [listId]
    );

    const values = useInfiniteQuery({
        queryKey: [
            entityListExploreQueryKey,
            listId,
            filters.status,
            debouncedValue,
        ],
        queryFn: ({pageParam}) =>
            getAttributeEntities({
                url: pageParam,
                list: listId,
                status: statuses[filters.status],
                value: debouncedValue || undefined,
                usage: true,
                limit: pageSize,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: list.isSuccess,
    });
    const items = useMemo(
        () => values.data?.pages.flatMap(p => p.items) ?? [],
        [values.data]
    );
    const total = values.data?.pages[0]?.total;

    const sentinelRef = useInfiniteScroll(
        scrollRef,
        () => void values.fetchNextPage(),
        !!values.hasNextPage && !values.isFetchingNextPage
    );

    const invalidate = () => {
        void queryClient.invalidateQueries({
            queryKey: [entityListExploreQueryKey, listId],
        });
        void queryClient.invalidateQueries({queryKey: ['entity-assets']});
    };

    const statusChoices: {id: StatusFilter; label: string}[] = [
        {
            id: 'approved',
            label: t('entity_list.explore.status_approved', 'Approved'),
        },
        {
            id: 'pending',
            label: t('entity_list.explore.status_pending', 'Pending'),
        },
        {id: 'all', label: t('entity_list.explore.status_all', 'All')},
    ];

    if (list.isError) {
        return (
            <EmptyState
                className="h-full"
                testId="entity-explore-not-found"
                icon={<CompassIcon />}
                title={t(
                    'entity_list.explore.not_found',
                    'This list does not exist or you cannot see it.'
                )}
            />
        );
    }

    const manageLink =
        canEdit && workspaceId ? (
            <Button asChild variant="ghost" size="sm">
                <Link
                    href={routes.entityListValues(workspaceId, listId)}
                    data-testid="entity-explore-manage"
                >
                    <ListIcon />{' '}
                    {t('entity_list.explore.manage_values', 'Manage values')}
                </Link>
            </Button>
        ) : null;

    return (
        <div className="flex h-full min-h-0 flex-col">
            <header className="flex shrink-0 flex-wrap items-center gap-2 border-b px-3 py-2">
                <CompassIcon className="size-5 text-primary" />
                {list.data ? (
                    <h1
                        className="font-semibold"
                        data-testid="entity-explore-title"
                    >
                        {list.data.name}
                    </h1>
                ) : (
                    <Skeleton className="h-5 w-40" />
                )}
                {workspace.data ? (
                    <span className="text-sm text-muted-foreground">
                        {workspace.data.displayName ?? workspace.data.name}
                    </span>
                ) : null}
                {total !== undefined ? (
                    <Badge variant="muted" data-testid="entity-explore-count">
                        {t('entity_list.explore.count', {
                            count: total,
                            defaultValue_one: '{{count}} value',
                            defaultValue_other: '{{count}} values',
                        })}
                    </Badge>
                ) : null}
                <div className="flex-1" />
                <FilterInput
                    value={filters.value}
                    onValueChange={value => update({value})}
                    placeholder={t(
                        'entity_list.explore.filter',
                        'Filter values…'
                    )}
                    className="w-56 text-sm"
                    data-testid="entity-explore-filter"
                />
                <div className="flex items-center gap-1" role="group">
                    {statusChoices.map(c => (
                        <Button
                            key={c.id}
                            size="sm"
                            variant={
                                filters.status === c.id ? 'secondary' : 'ghost'
                            }
                            aria-pressed={filters.status === c.id}
                            data-testid={`entity-explore-status-${c.id}`}
                            onClick={() => update({status: c.id})}
                        >
                            {c.label}
                        </Button>
                    ))}
                </div>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={invalidate}
                    loading={values.isRefetching && !values.isFetchingNextPage}
                >
                    <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                </Button>
                {manageLink}
            </header>

            <div ref={scrollRef} className="min-h-0 flex-1 overflow-y-auto">
                {definitionsLoaded && listDefinitions.length === 0 ? (
                    <EmptyState
                        className="h-full"
                        testId="entity-explore-no-definition"
                        icon={<CompassIcon />}
                        title={t(
                            'entity.preview.no_definition',
                            'No attribute is bound to this list'
                        )}
                        description={t(
                            'entity_list.explore.no_definition_help',
                            'The values show the assets holding them once an attribute of the workspace takes its values from this list.'
                        )}
                        action={manageLink}
                    />
                ) : !list.data || values.isLoading ? (
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-3 p-3">
                        {[...Array(8)].map((_, i) => (
                            <Skeleton key={i} className="h-40" />
                        ))}
                    </div>
                ) : items.length === 0 ? (
                    <EmptyState
                        className="h-full"
                        testId="entity-explore-empty"
                        icon={<CompassIcon />}
                        title={
                            debouncedValue
                                ? t(
                                      'entity_list.explore.empty_filter',
                                      'No value matches the filter'
                                  )
                                : t(
                                      'entity_list.explore.empty',
                                      'No value in this list'
                                  )
                        }
                    />
                ) : (
                    <>
                        <div
                            className="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-3 p-3"
                            data-testid="entity-explore-grid"
                        >
                            {workspaceId
                                ? items.map(e => (
                                      <EntityValueCard
                                          key={e.id}
                                          entity={e}
                                          list={list.data}
                                          workspaceId={workspaceId}
                                          canEdit={canEdit}
                                          onChanged={invalidate}
                                      />
                                  ))
                                : null}
                        </div>
                        <div ref={sentinelRef} className="h-px" />
                        {values.hasNextPage ? (
                            <div className="flex justify-center p-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    loading={values.isFetchingNextPage}
                                    onClick={() => void values.fetchNextPage()}
                                >
                                    {t('common.load_more', 'Load more')}
                                </Button>
                            </div>
                        ) : null}
                    </>
                )}
            </div>
        </div>
    );
}
