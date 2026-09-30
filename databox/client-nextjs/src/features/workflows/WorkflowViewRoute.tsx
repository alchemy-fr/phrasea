'use client';

import {useCallback, useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {usePathname} from 'next/navigation';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {WorkflowDetail} from '@/types/api';
import {cancelWorkflow, getWorkflow, rerunWorkflowJob} from '@/lib/api/misc';
import {
    RouteDialog,
    useCloseRouteDialog,
    useRouteDialogOrigin,
} from '@/components/modals/RouteDialog';
import {pathOf} from '@/components/modals/routeOrigins';
import {DialogTitle} from '@/components/ui/dialog';
import {Alert} from '@/components/ui/misc';
import {FullPageLoader} from '@/components/ui/loader';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {pushPath, splitRoutePath} from '@/lib/navigation/routePath';
import {routes, UNKNOWN_RENDITION} from '@/lib/routes';
import {useResizablePanel} from '@/hooks/useResizablePanel';
import {cn} from '@/lib/utils/cn';
import {WorkflowHeader} from './WorkflowHeader';
import {WorkflowGraph} from './WorkflowGraph';
import {JobDetailPanel} from './JobDetailPanel';
import {assetWorkflowsQueryKey, WorkflowRunsPanel} from './WorkflowRunsPanel';
import {findJob} from './workflowGraph';

/** Every URL of the screen: `/workflows/:id` */
const routeKey = routes.workflow('').replace(/\/$/, '');

const workflowPanelHash = '#panel=workflow';

/**
 * The asset of the run, on the workflow tab of its panel: the viewer the run
 * was opened from, when it was, so that it closes to where it was opened from.
 */
function assetViewUrl(origin: string, assetId: string): string {
    const viewer = routes.assetView(assetId, '');

    return pathOf(origin).startsWith(viewer)
        ? `${origin.split('#')[0]}${workflowPanelHash}`
        : routes.assetView(assetId, UNKNOWN_RENDITION, workflowPanelHash);
}

/**
 * Workflow run: the runs of its asset on the left, the node graph of its jobs
 * — refreshed in realtime on `workflow-{id}` — and the details of the job
 * selected on the right, from which it can be rerun.
 *
 * Closing leads to the asset, unless the run was opened from the list of all
 * the runs.
 */
export function WorkflowViewRoute({
    workflowId: initialId,
}: {
    workflowId: string;
}) {
    // Switching run is a `history.pushState`: the dialog stays open
    const pathname = usePathname();
    const [workflowId, setWorkflowId] = useState(initialId);
    const urlId = splitRoutePath(pathname, routeKey)?.[0];
    if (urlId && urlId !== workflowId) {
        setWorkflowId(urlId);
    }
    // Kept while switching: all the runs listed are on that asset
    const [assetId, setAssetId] = useState<string>();

    const closeTo = useCallback(
        (origin: string) =>
            assetId && pathOf(origin) !== routes.workflows()
                ? assetViewUrl(origin, assetId)
                : origin,
        [assetId]
    );

    return (
        <RouteDialog
            size="full"
            className="gap-0 p-0"
            routeKey={routeKey}
            closeTo={closeTo}
        >
            <WorkflowScreen
                workflowId={workflowId}
                assetId={assetId}
                onAsset={setAssetId}
            />
        </RouteDialog>
    );
}

function WorkflowScreen({
    workflowId,
    assetId,
    onAsset,
}: {
    workflowId: string;
    assetId: string | undefined;
    onAsset: (assetId: string | undefined) => void;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const queryKey = ['workflow', workflowId];
    const query = useQuery({queryKey, queryFn: () => getWorkflow(workflowId)});
    const [selected, setSelected] = useState<{
        workflowId: string;
        jobId: string;
    }>();
    const panel = useResizablePanel('workflow-job');
    const close = useCloseRouteDialog();
    const origin = useRouteDialogOrigin();

    useChannelEvent(`workflow-${workflowId}`, 'job_update', () => {
        void queryClient.invalidateQueries({queryKey});
        if (assetId) {
            void queryClient.invalidateQueries({
                queryKey: assetWorkflowsQueryKey(assetId),
            });
        }
    });

    const workflow = query.data;
    const workflowAssetId = workflow?.asset?.id;
    useEffect(() => {
        if (workflow) {
            onAsset(workflowAssetId);
        }
    }, [workflow, workflowAssetId, onAsset]);
    // A job selected in another run is not carried over
    const selectedJob =
        selected?.workflowId === workflowId
            ? findJob(workflow, selected.jobId)
            : undefined;
    const selectJob = (jobId: string | undefined) =>
        setSelected(jobId ? {workflowId, jobId} : undefined);

    const selectRun = useCallback(
        (id: string) => pushPath(routes.workflow(id)),
        []
    );

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
        <>
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
                    onOpenAsset={
                        workflowAssetId
                            ? () => close(assetViewUrl(origin, workflowAssetId))
                            : undefined
                    }
                />
            ) : (
                <div className="shrink-0 border-b px-4 py-4 pr-12">
                    <DialogTitle>{t('workflow.title', 'Workflow')}</DialogTitle>
                </div>
            )}
            <div className="relative flex min-h-0 flex-1">
                {assetId ? (
                    <WorkflowRunsPanel
                        assetId={assetId}
                        selectedId={workflowId}
                        onSelect={selectRun}
                    />
                ) : null}
                <div className="relative min-w-0 flex-1">
                    {workflow ? (
                        <WorkflowGraph
                            workflow={workflow}
                            selectedJobId={selectedJob?.jobId}
                            onSelectJob={selectJob}
                        />
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
                {selectedJob ? (
                    <>
                        <div
                            data-resize-handle
                            role="separator"
                            aria-orientation="vertical"
                            aria-label={t(
                                'workflow.resize_job_panel',
                                'Resize the job panel'
                            )}
                            tabIndex={0}
                            onPointerDown={panel.onPointerDown}
                            onKeyDown={panel.onKeyDown}
                            className={cn(
                                'hidden w-1 shrink-0 cursor-col-resize bg-border transition-colors hover:bg-primary/60 focus-visible:bg-primary focus-visible:outline-none sm:block',
                                panel.resizing && 'bg-primary'
                            )}
                        />
                        <JobDetailPanel
                            job={selectedJob}
                            width={panel.width}
                            onRerun={jobId =>
                                update(
                                    rerunWorkflowJob(workflowId, jobId),
                                    t(
                                        'workflow.job_rerun',
                                        'Job re-run requested'
                                    )
                                )
                            }
                            onClose={() => selectJob(undefined)}
                        />
                    </>
                ) : null}
            </div>
        </>
    );
}
