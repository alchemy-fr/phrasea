'use client';

import {useTranslation} from 'react-i18next';
import {PlayIcon} from 'lucide-react';
import {toast} from 'sonner';
import {Button} from '@alchemy/next-ui/ui/button';
import {Skeleton} from '@alchemy/next-ui/ui/misc';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@alchemy/next-ui/utils/cn';
import {formatJobDuration} from './jobStatus';
import {WorkflowStatusDot, workflowTitle} from './workflowLabel';
import {EventCategory, track, WorkflowEvent} from '@/lib/analytics/events';
import {useAssetWorkflowRuns} from './useAssetWorkflowRuns';

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
    const {query, runs, trigger, triggering} = useAssetWorkflowRuns(assetId, {
        onNewRun: onSelect,
    });

    const run = async () => {
        try {
            await trigger();
            track(EventCategory.Workflow, WorkflowEvent.Trigger);
            toast.success(t('workflow.triggered', 'Workflow triggered'));
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
                    loading={triggering}
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
