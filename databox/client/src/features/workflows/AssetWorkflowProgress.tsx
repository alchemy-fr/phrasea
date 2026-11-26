'use client';

import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {ExternalLinkIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {WorkflowStatus} from '@/types/api';
import {Progress} from '@alchemy/ui/ui/misc';
import {Tooltip} from '@alchemy/ui/ui/overlays';
import {cn} from '@alchemy/ui/utils/cn';
import {routes} from '@/lib/routes';
import {formatDateTime} from '@/lib/utils/format';
import {useAssetWorkflowProgress} from './useAssetWorkflowProgress';
import {
    WorkflowStatusDot,
    workflowStatusBadge,
    workflowTitle,
} from './workflowLabel';

type Props = {
    asset: Pick<Asset, 'id' | 'capabilities'>;
    /** Link to the graph of the run */
    interactive?: boolean;
    className?: string;
};

/**
 * Compact card over the media of an asset: the progress of its last workflow
 * run while it runs, then the status it ended with when it did not succeed
 * (a successful run hides the card). Editors only, like the runs themselves.
 */
export function AssetWorkflowProgress(props: Props) {
    if (!props.asset.capabilities.edit) {
        return null;
    }

    return <WorkflowProgressCard {...props} />;
}

function WorkflowProgressCard({asset, interactive, className}: Props) {
    const {t, i18n} = useTranslation();
    const {run, progress} = useAssetWorkflowProgress(asset);

    if (!run || run.status === WorkflowStatus.Success) {
        return null;
    }

    const running = run.status === WorkflowStatus.Started;
    const openLabel = t('workflow.progress.open', 'Open the run');
    const jobsLabel = progress
        ? t('workflow.progress.jobs', '{{done}}/{{total}} jobs', {
              done: progress.done,
              total: progress.total,
          })
        : undefined;
    const percent =
        progress && progress.total > 0
            ? Math.round((progress.done / progress.total) * 100)
            : undefined;

    return (
        <div
            data-testid="asset-workflow-progress"
            data-status={run.status}
            className={cn(
                'rounded-md bg-background/85 px-3 py-2 text-xs text-foreground shadow backdrop-blur',
                className
            )}
        >
            <div className="flex items-center gap-2">
                <WorkflowStatusDot status={run.status} />
                <span
                    className="min-w-0 flex-1 truncate font-medium"
                    title={run.name}
                >
                    {workflowTitle(run, t)}
                </span>
                {running ? null : workflowStatusBadge(run.status, t)}
                {interactive ? (
                    <Tooltip content={openLabel}>
                        <Link
                            href={routes.workflow(run.id)}
                            scroll={false}
                            aria-label={openLabel}
                            className="shrink-0 text-muted-foreground hover:text-foreground"
                        >
                            <ExternalLinkIcon className="size-3.5" />
                        </Link>
                    </Tooltip>
                ) : null}
            </div>
            {running ? (
                <div className="mt-2 space-y-1">
                    <Progress
                        value={percent}
                        indeterminate={percent === undefined}
                        getValueLabel={() => jobsLabel ?? ''}
                        aria-label={workflowTitle(run, t)}
                    />
                    {progress ? (
                        <div className="flex gap-2 text-muted-foreground">
                            <span className="shrink-0 tabular-nums">
                                {jobsLabel}
                            </span>
                            {progress.running.length > 0 ? (
                                <span className="min-w-0 truncate">
                                    {t(
                                        'workflow.progress.running',
                                        'Running: {{jobs}}',
                                        {
                                            jobs: progress.running
                                                .map(j => j.name)
                                                .join(', '),
                                        }
                                    )}
                                </span>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            ) : run.endedAt ? (
                <div className="mt-1 text-muted-foreground">
                    {formatDateTime(run.endedAt, 'short', i18n.language)}
                </div>
            ) : null}
        </div>
    );
}
