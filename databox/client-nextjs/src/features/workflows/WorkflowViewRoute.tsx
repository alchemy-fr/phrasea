'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {WorkflowDetail} from '@/types/api';
import {cancelWorkflow, getWorkflow, rerunWorkflowJob} from '@/lib/api/misc';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {DialogTitle} from '@/components/ui/dialog';
import {Alert} from '@/components/ui/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {WorkflowHeader} from './WorkflowHeader';
import {WorkflowGraph} from './WorkflowGraph';
import {JobDetailPanel} from './JobDetailPanel';
import {findJob} from './workflowGraph';

/**
 * Workflow run: its metadata and the node graph of its jobs, refreshed in
 * realtime on `workflow-{id}`. Selecting a job opens its details, from which
 * it can be rerun.
 */
export function WorkflowViewRoute({workflowId}: {workflowId: string}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const queryKey = ['workflow', workflowId];
    const query = useQuery({queryKey, queryFn: () => getWorkflow(workflowId)});
    const [selectedJobId, setSelectedJobId] = useState<string>();
    useChannelEvent(`workflow-${workflowId}`, 'job_update', () =>
        queryClient.invalidateQueries({queryKey})
    );
    const workflow = query.data;
    const selectedJob = findJob(workflow, selectedJobId);

    const update = async (
        request: Promise<WorkflowDetail>,
        success: string
    ): Promise<void> => {
        try {
            queryClient.setQueryData(queryKey, await request);
            toast.success(success);
        } catch (e: any) {
            toast.error(e?.message ?? t('common.error', 'An error occurred'));
        }
    };

    return (
        <RouteDialog size="full" className="gap-0 p-0">
            {workflow ? (
                <WorkflowHeader
                    workflow={workflow}
                    onRefresh={() => query.refetch()}
                    onCancel={() =>
                        update(
                            cancelWorkflow(workflowId),
                            t('workflow.cancelled', 'Workflow cancelled')
                        )
                    }
                />
            ) : (
                <DialogTitle className="border-b px-6 py-5">
                    {t('workflow.title', 'Workflow')}
                </DialogTitle>
            )}
            <div className="relative min-h-0 flex-1">
                {workflow ? (
                    <>
                        <WorkflowGraph
                            workflow={workflow}
                            selectedJobId={selectedJob?.jobId}
                            onSelectJob={setSelectedJobId}
                        />
                        {selectedJob ? (
                            <JobDetailPanel
                                job={selectedJob}
                                onRerun={jobId =>
                                    update(
                                        rerunWorkflowJob(workflowId, jobId),
                                        t(
                                            'workflow.job_rerun',
                                            'Job re-run requested'
                                        )
                                    )
                                }
                                onClose={() => setSelectedJobId(undefined)}
                            />
                        ) : null}
                    </>
                ) : query.isError ? (
                    <div className="p-6">
                        <Alert variant="destructive">
                            {(query.error as Error)?.message ||
                                t('common.error', 'An error occurred')}
                        </Alert>
                    </div>
                ) : (
                    <FullPageLoader />
                )}
            </div>
        </RouteDialog>
    );
}
