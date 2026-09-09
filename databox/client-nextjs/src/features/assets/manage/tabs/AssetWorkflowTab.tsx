'use client';

import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {useQuery} from '@tanstack/react-query';
import {ExternalLinkIcon, PlayIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AssetTabProps} from '../AssetManageRoute';
import {WorkflowStatus} from '@/types/api';
import {getAssetWorkflows} from '@/lib/api/misc';
import {triggerAssetWorkflow} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {Badge, Skeleton} from '@/components/ui/misc';
import {formatDateTime} from '@/lib/utils/format';
import {routes} from '@/lib/routes';

export function workflowStatusBadge(
    status: WorkflowStatus,
    t: (k: string, d: string) => string
) {
    switch (status) {
        case WorkflowStatus.Success:
            return (
                <Badge variant="success">
                    {t('workflow.status.success', 'Success')}
                </Badge>
            );
        case WorkflowStatus.Failure:
            return (
                <Badge variant="destructive">
                    {t('workflow.status.failure', 'Failure')}
                </Badge>
            );
        case WorkflowStatus.Cancelled:
            return (
                <Badge variant="muted">
                    {t('workflow.status.cancelled', 'Cancelled')}
                </Badge>
            );
        default:
            return (
                <Badge variant="warning">
                    {t('workflow.status.started', 'Started')}
                </Badge>
            );
    }
}

export function AssetWorkflowTab({asset}: AssetTabProps) {
    const {t, i18n} = useTranslation();
    const router = useRouter();
    const workflows = useQuery({
        queryKey: ['asset-workflows', asset.id],
        queryFn: () => getAssetWorkflows(asset.id),
    });

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={async () => {
                        try {
                            await triggerAssetWorkflow(asset.id);
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
            {workflows.data?.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('workflow.empty', 'No workflow ran for this asset')}
                </p>
            ) : null}
            <ul className="space-y-2">
                {workflows.data?.items.map(w => (
                    <li
                        key={w.id}
                        className="flex items-center gap-3 rounded-md border p-3 text-sm"
                    >
                        <div className="min-w-0 flex-1">
                            <div className="font-medium">{w.name}</div>
                            <div className="text-xs text-muted-foreground">
                                {formatDateTime(
                                    w.startedAt,
                                    'medium',
                                    i18n.language
                                )}
                                {w.completedAt
                                    ? ` → ${formatDateTime(w.completedAt, 'medium', i18n.language)}`
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
        </div>
    );
}
