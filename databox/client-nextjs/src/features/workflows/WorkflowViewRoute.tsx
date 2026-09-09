'use client';

import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {BanIcon, RefreshCwIcon, RotateCcwIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {WorkflowJob} from '@/types/api';
import {cancelWorkflow, getWorkflow, rerunWorkflowJob} from '@/lib/api/misc';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {DialogBody, DialogHeader, DialogTitle} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Badge} from '@/components/ui/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {workflowStatusBadge} from '@/features/assets/manage/tabs/AssetWorkflowTab';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {WorkflowStatus} from '@/types/api';

const jobStatusLabel: Record<number, {label: string; className: string}> = {
    0: {label: 'Triggered', className: 'bg-muted text-muted-foreground'},
    1: {label: 'Success', className: 'bg-success/20 text-success-foreground'},
    2: {label: 'Failure', className: 'bg-destructive/20 text-destructive'},
    3: {label: 'Skipped', className: 'bg-muted text-muted-foreground'},
    4: {label: 'Running', className: 'bg-warning/30 text-warning-foreground'},
    5: {label: 'Error', className: 'bg-destructive/20 text-destructive'},
    6: {label: 'Cancelled', className: 'bg-muted text-muted-foreground'},
};

/**
 * Workflow view: jobs grouped by stage, realtime updates on `workflow-{id}`.
 */
export function WorkflowViewRoute({workflowId}: {workflowId: string}) {
    const {t, i18n} = useTranslation();
    const queryClient = useQueryClient();
    const queryKey = ['workflow', workflowId];
    const query = useQuery({queryKey, queryFn: () => getWorkflow(workflowId)});
    useChannelEvent(`workflow-${workflowId}`, 'job_update', () =>
        queryClient.invalidateQueries({queryKey})
    );
    const workflow = query.data;

    const stages: WorkflowJob[][] =
        workflow?.stages?.map(s => s.jobs) ??
        groupByStage(workflow?.jobs ?? []);

    return (
        <RouteDialog size="xl" className="h-[85dvh]">
            <DialogHeader className="flex-row items-center gap-3 pr-8">
                <DialogTitle className="flex-1 truncate">
                    {workflow?.name ?? t('workflow.title', 'Workflow')}
                </DialogTitle>
                {workflow ? workflowStatusBadge(workflow.status, t) : null}
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => query.refetch()}
                >
                    <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                </Button>
                {workflow?.status === WorkflowStatus.Started ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={async () => {
                            await cancelWorkflow(workflowId);
                            toast.success(
                                t('workflow.cancelled', 'Workflow cancelled')
                            );
                            void query.refetch();
                        }}
                    >
                        <BanIcon /> {t('common.cancel', 'Cancel')}
                    </Button>
                ) : null}
            </DialogHeader>
            <DialogBody>
                {!workflow ? (
                    <FullPageLoader />
                ) : (
                    <div className="flex gap-6 overflow-x-auto pb-4">
                        {stages.map((jobs, i) => (
                            <div
                                key={i}
                                className="flex min-w-64 flex-col gap-3"
                            >
                                <div className="text-xs font-semibold text-muted-foreground uppercase">
                                    {t('workflow.stage', 'Stage {{n}}', {
                                        n: i + 1,
                                    })}
                                </div>
                                {jobs.map(job => {
                                    const st =
                                        jobStatusLabel[job.status] ??
                                        jobStatusLabel[0];

                                    return (
                                        <div
                                            key={job.id}
                                            className="rounded-md border bg-card p-3 text-sm shadow-sm"
                                        >
                                            <div className="mb-1 flex items-center gap-2">
                                                <span className="min-w-0 flex-1 truncate font-medium">
                                                    {job.name}
                                                </span>
                                                <Badge
                                                    className={cn(
                                                        'border-transparent',
                                                        st.className
                                                    )}
                                                >
                                                    {st.label}
                                                </Badge>
                                            </div>
                                            {job.needs &&
                                            job.needs.length > 0 ? (
                                                <div className="text-xs text-muted-foreground">
                                                    {t(
                                                        'workflow.needs',
                                                        'needs'
                                                    )}
                                                    : {job.needs.join(', ')}
                                                </div>
                                            ) : null}
                                            <div className="text-xs text-muted-foreground">
                                                {job.startedAt
                                                    ? formatDateTime(
                                                          job.startedAt,
                                                          'short',
                                                          i18n.language
                                                      )
                                                    : ''}
                                                {job.completedAt
                                                    ? ` → ${formatDateTime(job.completedAt, 'short', i18n.language)}`
                                                    : ''}
                                            </div>
                                            {job.errors &&
                                            job.errors.length > 0 ? (
                                                <pre className="mt-1 max-h-32 overflow-auto rounded bg-destructive/10 p-2 text-[11px] text-destructive">
                                                    {job.errors.join('\n')}
                                                </pre>
                                            ) : null}
                                            {[2, 5].includes(job.status) ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-2"
                                                    onClick={async () => {
                                                        await rerunWorkflowJob(
                                                            workflowId,
                                                            job.id
                                                        );
                                                        toast.success(
                                                            t(
                                                                'workflow.job_rerun',
                                                                'Job re-run requested'
                                                            )
                                                        );
                                                        void query.refetch();
                                                    }}
                                                >
                                                    <RotateCcwIcon />{' '}
                                                    {t(
                                                        'workflow.rerun',
                                                        'Rerun'
                                                    )}
                                                </Button>
                                            ) : null}
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                )}
            </DialogBody>
        </RouteDialog>
    );
}

function groupByStage(jobs: WorkflowJob[]): WorkflowJob[][] {
    const map = new Map<number, WorkflowJob[]>();
    jobs.forEach(j => {
        const s = j.stage ?? 0;
        map.set(s, [...(map.get(s) ?? []), j]);
    });

    return [...map.keys()].sort((a, b) => a - b).map(k => map.get(k)!);
}
