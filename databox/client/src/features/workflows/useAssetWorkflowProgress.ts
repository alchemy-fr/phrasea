'use client';

import {useCallback, useMemo} from 'react';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import type {Asset} from '@/types/api';
import {WorkflowStatus} from '@/types/api';
import {getAssetWorkflows, getWorkflow} from '@/lib/api/misc';
import {useChannelEvent, useRealtime} from '@/lib/realtime/RealtimeProvider';
import {assetChannel, workflowChannel} from '@/lib/realtime/channels';
import {assetWorkflowsQueryKey} from './useAssetWorkflowRuns';
import {computeWorkflowProgress} from './workflowProgress';

/**
 * How often a running run is read again: the realtime events may be missed
 * (reconnection, event sent before the subscription), so they are not the
 * only source of the progress.
 */
export const RUNNING_POLL_MS = 3_000;
export const RUNNING_POLL_REALTIME_MS = 30_000;

/**
 * The last run started on the asset and, while it runs, the progress over its
 * jobs, kept up to date by the realtime events of the asset and of the run,
 * and read again periodically while it runs.
 *
 * The runs of an asset are readable by its editors only: nothing is requested
 * without the edit capability.
 */
export function useAssetWorkflowProgress(
    asset: Pick<Asset, 'id' | 'capabilities'>,
    {enabled = true}: {enabled?: boolean} = {}
) {
    const queryClient = useQueryClient();
    const allowed = enabled && asset.capabilities.edit;
    const assetKey = assetWorkflowsQueryKey(asset.id);

    const {enabled: realtimeEnabled} = useRealtime();
    const pollInterval = realtimeEnabled
        ? RUNNING_POLL_REALTIME_MS
        : RUNNING_POLL_MS;

    const latest = useQuery({
        queryKey: [...assetKey, 'latest'],
        queryFn: () => getAssetWorkflows(asset.id),
        select: page => page.items[0],
        staleTime: 10_000,
        enabled: allowed,
        refetchInterval: query =>
            query.state.data?.items[0]?.status === WorkflowStatus.Started
                ? pollInterval
                : false,
    });
    const run = allowed ? latest.data : undefined;
    const runId = run?.id;
    const running = run?.status === WorkflowStatus.Started;

    // Same query as the graph of the run, which shares its cache
    const detailQuery = useQuery({
        queryKey: ['workflow', runId],
        queryFn: () => getWorkflow(runId!),
        enabled: running,
        staleTime: 0,
        refetchInterval: running ? pollInterval : false,
    });
    const detail = running ? detailQuery.data : undefined;
    const progress = useMemo(
        () => (detail ? computeWorkflowProgress(detail) : undefined),
        [detail]
    );

    const invalidateRuns = useCallback(() => {
        void queryClient.invalidateQueries({
            queryKey: assetWorkflowsQueryKey(asset.id),
        });
    }, [queryClient, asset.id]);
    const invalidateDetail = useCallback(() => {
        void queryClient.invalidateQueries({queryKey: ['workflow', runId]});
    }, [queryClient, runId]);
    const onRunUpdate = useCallback(() => {
        invalidateDetail();
        invalidateRuns();
    }, [invalidateDetail, invalidateRuns]);

    const channel = allowed ? assetChannel(asset.id) : undefined;
    useChannelEvent(channel, 'workflow_update', invalidateRuns);
    useChannelEvent(channel, 'asset_ingested', invalidateRuns);

    const runChannel = running && runId ? workflowChannel(runId) : undefined;
    useChannelEvent(runChannel, 'job_update', invalidateDetail);
    // Sent once the status of the run is saved, unlike the last job_update
    useChannelEvent(runChannel, 'workflow_update', onRunUpdate);

    return {
        run,
        detail,
        progress,
        /** The progress of the running run is still loading */
        loading: running && !detail,
    };
}
