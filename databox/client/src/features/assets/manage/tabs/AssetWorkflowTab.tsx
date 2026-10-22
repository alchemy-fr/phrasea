'use client';

import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {useInfiniteQuery} from '@tanstack/react-query';
import {ExternalLinkIcon, PlayIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AssetTabProps} from '../types';
import {getAssetWorkflows} from '@/lib/api/misc';
import {assetWorkflowsQueryKey} from '@/features/workflows/WorkflowRunsPanel';
import {triggerAssetWorkflow} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {formatDateTime} from '@/lib/utils/format';
import {routes} from '@/lib/routes';
import {
    workflowStatusBadge,
    workflowTitle,
} from '@/features/workflows/workflowLabel';
import {EventCategory, track, WorkflowEvent} from '@/lib/analytics/events';

export function AssetWorkflowTab({asset}: AssetTabProps) {
    const {t, i18n} = useTranslation();
    const router = useRouter();
    const workflows = useInfiniteQuery({
        queryKey: [...assetWorkflowsQueryKey(asset.id), 'pages'],
        queryFn: ({pageParam}) => getAssetWorkflows(asset.id, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const runs = workflows.data?.pages.flatMap(p => p.items);

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={async () => {
                        try {
                            await triggerAssetWorkflow(asset.id);
                            track(
                                EventCategory.Workflow,
                                WorkflowEvent.Trigger
                            );
                            toast.success(
                                t('workflow.triggered', 'Workflow triggered')
                            );
                            setTimeout(() => workflows.refetch(), 1500);
                        } catch (e: any) {
                            toast.error(e?.message);
                        }
                    }}
                >
                    <PlayIcon />{' '}
                    {t('workflow.trigger_again', 'Trigger workflow again')}
                </Button>
            </div>
            {workflows.isLoading ? <Skeleton className="h-24" /> : null}
            {runs?.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('workflow.empty', 'No workflow ran for this asset')}
                </p>
            ) : null}
            <ul className="space-y-2">
                {runs?.map(w => (
                    <li
                        key={w.id}
                        className="flex items-center gap-3 rounded-md border p-3 text-sm"
                    >
                        <div className="min-w-0 flex-1">
                            <div className="font-medium" title={w.name}>
                                {workflowTitle(w, t)}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {formatDateTime(
                                    w.startedAt,
                                    'medium',
                                    i18n.language
                                )}
                                {w.endedAt
                                    ? ` → ${formatDateTime(w.endedAt, 'medium', i18n.language)}`
                                    : ''}
                            </div>
                        </div>
                        {workflowStatusBadge(w.status, t)}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.push(routes.workflow(w.id))}
                        >
                            <ExternalLinkIcon /> {t('common.view', 'View')}
                        </Button>
                    </li>
                ))}
            </ul>
            {workflows.hasNextPage ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-full"
                    loading={workflows.isFetchingNextPage}
                    onClick={() => workflows.fetchNextPage()}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </div>
    );
}
