'use client';

import {useTranslation} from 'react-i18next';
import Link from 'next/link';
import {usePathname, useRouter, useSearchParams} from 'next/navigation';
import {useInfiniteQuery} from '@tanstack/react-query';
import {ImageIcon, RefreshCwIcon, WorkflowIcon} from 'lucide-react';
import {WorkflowStatus} from '@/types/api';
import {getWorkflows} from '@/lib/api/misc';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {Button} from '@/components/ui/button';
import {
    EmptyState,
    Skeleton,
    Tabs,
    TabsList,
    TabsTrigger,
} from '@/components/ui/misc';
import {usePageTrail} from '@/components/layout/layoutStore';
import {formatDateTime} from '@/lib/utils/format';
import {routes} from '@/lib/routes';
import {formatJobDuration} from './jobStatus';
import {
    WorkflowStatusDot,
    workflowStatusBadge,
    workflowTitle,
} from './workflowLabel';

const statusFilters = {
    started: WorkflowStatus.Started,
    success: WorkflowStatus.Success,
    failure: WorkflowStatus.Failure,
    cancelled: WorkflowStatus.Cancelled,
} as const;

type StatusFilter = keyof typeof statusFilters;

function isStatusFilter(value: string | null): value is StatusFilter {
    return !!value && value in statusFilters;
}

/**
 * Every workflow run of the instance (admins), the last one first, filtered
 * by status (`?status=`, kept when coming back from a run).
 */
export function WorkflowsScreen() {
    const {t, i18n} = useTranslation();
    const {hasRole} = useAuth();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);
    const router = useRouter();
    const pathname = usePathname();
    const params = useSearchParams();
    const statusParam = params.get('status');
    const filter: StatusFilter | 'all' = isStatusFilter(statusParam)
        ? statusParam
        : 'all';
    const status = filter === 'all' ? undefined : statusFilters[filter];

    const runs = useInfiniteQuery({
        queryKey: ['workflows', status],
        queryFn: ({pageParam}) => getWorkflows({status, url: pageParam}),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: isAdmin,
    });
    const items = runs.data?.pages.flatMap(p => p.items);

    // Where the user is, shown in the top bar with the way back to the assets
    usePageTrail(t('workflow.all.title', 'Workflows'), routes.workflows());

    const setFilter = (value: string) =>
        router.replace(
            value === 'all' ? pathname : `${pathname}?status=${value}`,
            {scroll: false}
        );

    return (
        <RequireAuth>
            {!isAdmin ? (
                <EmptyState
                    className="h-full"
                    title={t(
                        'common.forbidden',
                        'You are not allowed to access this page'
                    )}
                />
            ) : (
                <div
                    data-testid="workflows-screen"
                    className="mx-auto w-full max-w-5xl space-y-4 overflow-y-auto p-4"
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <WorkflowIcon className="size-5 text-muted-foreground" />
                        <h1 className="flex-1 text-lg font-semibold">
                            {t('workflow.all.title', 'Workflows')}
                        </h1>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => runs.refetch()}
                        >
                            <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                        </Button>
                    </div>
                    <Tabs value={filter} onValueChange={setFilter}>
                        <TabsList>
                            <TabsTrigger value="all">
                                {t('workflow.all.filter_all', 'All')}
                            </TabsTrigger>
                            <TabsTrigger value="started">
                                {t('workflow.status.started', 'Started')}
                            </TabsTrigger>
                            <TabsTrigger value="success">
                                {t('workflow.status.success', 'Success')}
                            </TabsTrigger>
                            <TabsTrigger value="failure">
                                {t('workflow.status.failure', 'Failure')}
                            </TabsTrigger>
                            <TabsTrigger value="cancelled">
                                {t('workflow.status.cancelled', 'Cancelled')}
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <ul className="divide-y rounded-md border bg-card">
                        {runs.isLoading
                            ? [...Array(4)].map((_, i) => (
                                  <li key={i} className="p-3">
                                      <Skeleton className="h-10" />
                                  </li>
                              ))
                            : null}
                        {items?.map(w => {
                            const duration = formatJobDuration(
                                w.duration ?? undefined
                            );

                            return (
                                <li
                                    key={w.id}
                                    data-testid="workflows-row"
                                    className="flex items-center gap-3 px-3 py-2 text-sm hover:bg-accent/40"
                                >
                                    <WorkflowStatusDot status={w.status} />
                                    <Link
                                        href={routes.workflow(w.id)}
                                        scroll={false}
                                        className="min-w-0 flex-1"
                                    >
                                        <span
                                            className="block truncate font-medium hover:underline"
                                            title={w.name}
                                        >
                                            {workflowTitle(w, t)}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {w.startedAt
                                                ? formatDateTime(
                                                      w.startedAt,
                                                      'medium',
                                                      i18n.language
                                                  )
                                                : null}
                                            {duration ? ` · ${duration}` : null}
                                            {w.eventName
                                                ? ` · ${w.eventName}`
                                                : null}
                                        </span>
                                    </Link>
                                    {w.assetId ? (
                                        <Link
                                            href={routes.assetView(w.assetId)}
                                            scroll={false}
                                            className="hidden max-w-[30ch] min-w-0 items-center gap-1.5 truncate text-muted-foreground hover:text-foreground hover:underline sm:inline-flex"
                                        >
                                            <ImageIcon className="size-3.5 shrink-0" />
                                            <span className="truncate">
                                                {w.assetName ||
                                                    t('nav.asset', 'Asset')}
                                            </span>
                                        </Link>
                                    ) : null}
                                    {workflowStatusBadge(w.status, t)}
                                </li>
                            );
                        })}
                        {items?.length === 0 ? (
                            <li>
                                <EmptyState
                                    title={t(
                                        'workflow.all.empty',
                                        'No workflow run'
                                    )}
                                />
                            </li>
                        ) : null}
                    </ul>
                    {runs.hasNextPage ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => runs.fetchNextPage()}
                            loading={runs.isFetchingNextPage}
                        >
                            {t('common.load_more', 'Load more')}
                        </Button>
                    ) : null}
                </div>
            )}
        </RequireAuth>
    );
}
