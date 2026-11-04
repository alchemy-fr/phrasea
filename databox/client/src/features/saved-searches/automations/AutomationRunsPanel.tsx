'use client';

import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import {HistoryIcon} from 'lucide-react';
import type {TFunction} from 'i18next';
import {
    AutomationRun,
    AutomationRunStatus,
    SavedSearchAutomation,
} from '@/types/api';
import {getAutomationRuns} from '@/lib/api/automations';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
    Badge,
    EmptyState,
    Skeleton,
} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {formatDateTime} from '@/lib/utils/format';
import {getActionDefinition} from './actions/registry';
import {automationRunsQueryKey} from './queryKeys';

const RUNNING = [AutomationRunStatus.Pending, AutomationRunStatus.Running];

export function runStatusVariant(
    status: AutomationRunStatus | null | undefined
): 'success' | 'warning' | 'destructive' | 'muted' | 'secondary' {
    switch (status) {
        case AutomationRunStatus.Success:
            return 'success';
        case AutomationRunStatus.Partial:
            return 'warning';
        case AutomationRunStatus.Failed:
            return 'destructive';
        case AutomationRunStatus.Pending:
        case AutomationRunStatus.Running:
            return 'secondary';
        default:
            return 'muted';
    }
}

export function runStatusLabel(
    status: AutomationRunStatus,
    t: TFunction
): string {
    switch (status) {
        case AutomationRunStatus.Pending:
            return t('automation.run.status.pending', 'Pending');
        case AutomationRunStatus.Running:
            return t('automation.run.status.running', 'Running');
        case AutomationRunStatus.Success:
            return t('automation.run.status.success', 'Success');
        case AutomationRunStatus.Partial:
            return t('automation.run.status.partial', 'Partial');
        case AutomationRunStatus.Failed:
            return t('automation.run.status.failed', 'Failed');
        case AutomationRunStatus.Skipped:
            return t('automation.run.status.skipped', 'Skipped');
        case AutomationRunStatus.Cancelled:
            return t('automation.run.status.cancelled', 'Cancelled');
    }
}

export function RunStatusBadge({status}: {status: AutomationRunStatus}) {
    const {t} = useTranslation();

    return (
        <Badge variant={runStatusVariant(status)}>
            {runStatusLabel(status, t)}
        </Badge>
    );
}

type Props = ModalProps & {
    automation: SavedSearchAutomation;
};

/**
 * History of the runs of an automation, refreshed while a run is in progress.
 */
export function AutomationRunsDialog({open, onOpenChange, automation}: Props) {
    const {t} = useTranslation();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('automation.runs.title', 'History of "{{name}}"', {
                name: automation.name,
            })}
            hideSubmit
            cancelLabel={t('common.close', 'Close')}
        >
            <AutomationRunsPanel automationId={automation.id} />
        </FormDialog>
    );
}

export function AutomationRunsPanel({automationId}: {automationId: string}) {
    const {t, i18n} = useTranslation();
    const runs = useInfiniteQuery({
        queryKey: automationRunsQueryKey(automationId),
        queryFn: ({pageParam}) =>
            getAutomationRuns(automationId, {next: pageParam}),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        refetchInterval: query =>
            query.state.data?.pages.some(p =>
                p.items.some(r => RUNNING.includes(r.status))
            )
                ? 5000
                : false,
    });
    const items = runs.data?.pages.flatMap(p => p.items) ?? [];

    if (runs.isLoading) {
        return (
            <div className="space-y-2">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
            </div>
        );
    }

    if (items.length === 0) {
        return (
            <EmptyState
                icon={<HistoryIcon />}
                title={t('automation.runs.empty', 'No run yet')}
            />
        );
    }

    return (
        <div>
            <Accordion type="multiple" data-testid="automation-runs">
                {items.map(run => (
                    <AccordionItem key={run.id} value={run.id}>
                        <AccordionTrigger>
                            <RunSummary run={run} lang={i18n.language} />
                        </AccordionTrigger>
                        <AccordionContent>
                            <RunDetails run={run} lang={i18n.language} />
                        </AccordionContent>
                    </AccordionItem>
                ))}
            </Accordion>
            {runs.hasNextPage ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="mt-2 w-full"
                    onClick={() => runs.fetchNextPage()}
                    loading={runs.isFetchingNextPage}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </div>
    );
}

function RunSummary({run, lang}: {run: AutomationRun; lang: string}) {
    const {t} = useTranslation();

    return (
        <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <RunStatusBadge status={run.status} />
            {run.dryRun ? (
                <Badge variant="outline">
                    {t('automation.run.dry_run', 'Dry run')}
                </Badge>
            ) : null}
            <span className="text-muted-foreground">
                {run.trigger === 'schedule'
                    ? t('automation.run.trigger.schedule', 'Scheduled')
                    : t('automation.run.trigger.manual', 'Manual')}
            </span>
            <span>
                {formatDateTime(run.startedAt ?? run.createdAt, 'medium', lang)}
            </span>
            <span className="ml-auto text-xs font-normal text-muted-foreground">
                {t(
                    'automation.run.counts',
                    '{{matched}} matched · {{new}} new · {{processed}} processed · {{failed}} failed',
                    {
                        matched: run.matchedCount,
                        new: run.newCount,
                        processed: run.processedCount,
                        failed: run.failedCount,
                    }
                )}
            </span>
        </span>
    );
}

function RunDetails({run, lang}: {run: AutomationRun; lang: string}) {
    const {t} = useTranslation();

    return (
        <div className="space-y-3">
            <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-xs">
                <dt className="text-muted-foreground">
                    {t('automation.run.started_at', 'Started at')}
                </dt>
                <dd>{formatDateTime(run.startedAt, 'medium', lang) || '—'}</dd>
                <dt className="text-muted-foreground">
                    {t('automation.run.ended_at', 'Ended at')}
                </dt>
                <dd>{formatDateTime(run.endedAt, 'medium', lang) || '—'}</dd>
                {run.initiator ? (
                    <>
                        <dt className="text-muted-foreground">
                            {t('automation.run.initiator', 'Run by')}
                        </dt>
                        <dd>{run.initiator.username}</dd>
                    </>
                ) : null}
            </dl>
            {run.error ? (
                <p className="rounded-md bg-destructive/10 p-2 text-xs text-destructive">
                    {run.error}
                </p>
            ) : null}
            {run.actionResults.map((result, i) => {
                const definition = getActionDefinition(result.type);

                return (
                    <div key={i} className="rounded-md border p-2 text-xs">
                        <div className="flex flex-wrap items-center gap-2 font-medium">
                            {definition?.label(t) ?? result.type}
                            <Badge
                                variant={runStatusVariant(
                                    result.status as AutomationRunStatus
                                )}
                            >
                                {runStatusLabel(
                                    result.status as AutomationRunStatus,
                                    t
                                )}
                            </Badge>
                            <span className="ml-auto font-normal text-muted-foreground">
                                {t(
                                    'automation.run.action_counts',
                                    '{{processed}} processed · {{skipped}} skipped · {{failed}} failed',
                                    {
                                        processed: result.processed,
                                        skipped: result.skipped,
                                        failed: result.failed,
                                    }
                                )}
                            </span>
                        </div>
                        {result.errors.length > 0 ? (
                            <ul className="mt-1 max-h-40 space-y-0.5 overflow-y-auto font-mono text-muted-foreground">
                                {result.errors.map((e, j) => (
                                    <li key={j}>
                                        {e.assetId ? `${e.assetId}: ` : ''}
                                        {e.message}
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                );
            })}
        </div>
    );
}
