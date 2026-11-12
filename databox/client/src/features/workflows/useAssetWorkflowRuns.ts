'use client';

import {useCallback, useEffect, useMemo, useState} from 'react';
import {useInfiniteQuery, useQueryClient} from '@tanstack/react-query';
import {getAssetWorkflows} from '@/lib/api/misc';
import {triggerAssetWorkflow} from '@/lib/api/assets';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {assetChannel} from '@/lib/realtime/channels';

/** A new run is looked for this long after it was requested */
const newRunTimeout = 20000;

/** Prefix of every query on the runs of an asset */
export function assetWorkflowsQueryKey(assetId: string) {
    return ['asset-workflows', assetId];
}

/** The runs listed when a new one was requested, until it shows up */
type AwaitedRun = {
    assetId: string;
    known: Set<string>;
};

/**
 * Every workflow run on the asset, the last one first, kept up to date by the
 * `workflow_update` events of the asset channel, and the action to run the
 * ingest again.
 *
 * `trigger` rejects with the API error (409 while an ingest of the asset is
 * still running); `triggering` stays on until the new run is listed, so that a
 * second click cannot run it twice.
 */
export function useAssetWorkflowRuns(
    assetId: string,
    {onNewRun}: {onNewRun?: (workflowId: string) => void} = {}
) {
    const queryClient = useQueryClient();
    const [requesting, setRequesting] = useState(false);
    const [awaitedRun, setAwaitedRun] = useState<AwaitedRun>();
    // Left behind when the viewer moves to another asset
    const awaited =
        awaitedRun?.assetId === assetId ? awaitedRun.known : undefined;

    const query = useInfiniteQuery({
        queryKey: [...assetWorkflowsQueryKey(assetId), 'pages'],
        queryFn: ({pageParam}) => getAssetWorkflows(assetId, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        // Coming back to an asset reloads its runs, which may have ended meanwhile
        staleTime: 0,
        refetchInterval: awaited ? 1000 : false,
    });
    const runs = useMemo(
        () => query.data?.pages.flatMap(p => p.items),
        [query.data]
    );

    const invalidate = useCallback(() => {
        void queryClient.invalidateQueries({
            queryKey: assetWorkflowsQueryKey(assetId),
        });
    }, [queryClient, assetId]);
    const channel = assetChannel(assetId);
    useChannelEvent(channel, 'workflow_update', invalidate);
    useChannelEvent(channel, 'asset_ingested', invalidate);

    useEffect(() => {
        if (!awaited) {
            return;
        }
        const run = runs?.find(r => !awaited.has(r.id));
        if (run) {
            setAwaitedRun(undefined);
            onNewRun?.(run.id);
        }
    }, [runs, awaited, onNewRun]);

    useEffect(() => {
        if (!awaited) {
            return;
        }
        const timer = setTimeout(() => setAwaitedRun(undefined), newRunTimeout);

        return () => clearTimeout(timer);
    }, [awaited]);

    const trigger = useCallback(async (): Promise<void> => {
        const known = new Set(runs?.map(r => r.id) ?? []);
        setRequesting(true);
        try {
            await triggerAssetWorkflow(assetId);
        } finally {
            setRequesting(false);
        }
        setAwaitedRun({assetId, known});
    }, [assetId, runs]);

    return {
        query,
        runs,
        trigger,
        triggering: requesting || !!awaited,
    };
}
