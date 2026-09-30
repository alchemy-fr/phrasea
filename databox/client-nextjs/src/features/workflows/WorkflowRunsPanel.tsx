'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import {PlayIcon} from 'lucide-react';
import {toast} from 'sonner';
import {getWorkflows} from '@/lib/api/misc';
import {triggerAssetWorkflow} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {formatJobDuration} from './jobStatus';
import {WorkflowStatusDot, workflowTitle} from './workflowLabel';

/** A new run is looked for this long after it was requested */
const newRunTimeout = 20000;

/** Prefix shared with the workflow tab of the asset panel */
export function assetWorkflowsQueryKey(assetId: string) {
    return ['asset-workflows', assetId];
}

/**
 * Every workflow run on the asset, the last one first, and the action to run
 * the ingest again: the new run is selected once it shows up.
 */
export function WorkflowRunsPanel({
    assetId,
    selectedId,
    onSelect,
}: {
    assetId: string;
    selectedId: string;
    onSelect: (workflowId: string) => void;
}) {
    const {t, i18n} = useTranslation();
    // The runs listed when a new one was requested, until it shows up
    const [awaited, setAwaited] = useState<Set<string>>();
    const query = useInfiniteQuery({
        queryKey: [...assetWorkflowsQueryKey(assetId), 'pages'],
        queryFn: ({pageParam}) =>
            getWorkflows({asset: assetId, url: pageParam}),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        refetchInterval: awaited ? 1000 : false,
    });
    const runs = useMemo(
        () => query.data?.pages.flatMap(p => p.items),
        [query.data]
    );

    useEffect(() => {
        if (!awaited) {
            return;
        }
        const run = runs?.find(r => !awaited.has(r.id));
        if (run) {
            setAwaited(undefined);
            onSelect(run.id);
        }
    }, [runs, awaited, onSelect]);

    useEffect(() => {
        if (!awaited) {
            return;
        }
        const timer = setTimeout(() => setAwaited(undefined), newRunTimeout);

        return () => clearTimeout(timer);
    }, [awaited]);

    const run = async () => {
        try {
            const known = new Set(runs?.map(r => r.id) ?? []);
            await triggerAssetWorkflow(assetId);
            toast.success(t('workflow.triggered', 'Workflow triggered'));
            setAwaited(known);
        } catch (e: any) {
            toast.error(e?.message ?? t('common.error', 'An error occurred'));
        }
    };

    return (
        <nav
            data-testid="workflow-runs"
            aria-label={t('workflow.runs', 'Runs')}
            className="hidden w-64 shrink-0 flex-col border-r bg-muted/30 md:flex"
        >
            <div className="border-b p-3">
                <Button
                    size="sm"
                    className="w-full"
                    loading={!!awaited}
                    onClick={run}
                >
                    <PlayIcon /> {t('workflow.run_new', 'Run a new workflow')}
                </Button>
            </div>
            <ul className="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-2">
                {query.isLoading
                    ? [...Array(3)].map((_, i) => (
                          <li key={i}>
                              <Skeleton className="h-12" />
                          </li>
                      ))
                    : null}
                {runs?.map(r => {
                    const selected = r.id === selectedId;
                    const duration = formatJobDuration(r.duration ?? undefined);

                    return (
                        <li key={r.id}>
                            <button
                                type="button"
                                data-testid="workflow-run"
                                aria-current={selected ? 'page' : undefined}
                                onClick={() => onSelect(r.id)}
                                className={cn(
                                    'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm transition-colors hover:bg-accent',
                                    selected &&
                                        'bg-accent font-medium text-accent-foreground'
                                )}
                            >
                                <WorkflowStatusDot status={r.status} />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate">
                                        {workflowTitle(r, t)}
                                    </span>
                                    <span className="block truncate text-xs font-normal text-muted-foreground">
                                        {r.startedAt
                                            ? formatDateTime(
                                                  r.startedAt,
                                                  'relative',
                                                  i18n.language
                                              )
                                            : null}
                                        {duration ? ` · ${duration}` : null}
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}
                {query.hasNextPage ? (
                    <li>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-full"
                            loading={query.isFetchingNextPage}
                            onClick={() => query.fetchNextPage()}
                        >
                            {t('common.load_more', 'Load more')}
                        </Button>
                    </li>
                ) : null}
            </ul>
        </nav>
    );
}
