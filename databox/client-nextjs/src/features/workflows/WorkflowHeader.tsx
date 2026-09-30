'use client';

import {Fragment, useState, type ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {
    BanIcon,
    ChevronDownIcon,
    ClockIcon,
    ImageIcon,
    RefreshCwIcon,
    TimerIcon,
    ZapIcon,
} from 'lucide-react';
import type {WorkflowDetail} from '@/types/api';
import {DialogHeader, DialogTitle} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {CopiableText} from '@/components/ui/copy-button';
import {Tooltip} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';
import {JsonSection, RelativeDate} from './workflowDetails';
import {canCancelWorkflow, formatJobDuration, hasData} from './jobStatus';
import {workflowStatusBadge, workflowTitle} from './workflowLabel';

/**
 * An item of the metadata line, its label in the tooltip of its icon (the
 * value may have its own: the dates)
 */
function Meta({
    label,
    icon,
    children,
}: {
    label: string;
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <span className="inline-flex min-w-0 items-center gap-1.5">
            <Tooltip content={label}>
                <span className="inline-flex [&_svg]:size-3.5 [&_svg]:shrink-0">
                    {icon}
                </span>
            </Tooltip>
            <span className="sr-only">{label}</span>
            {children}
        </span>
    );
}

/**
 * The run (`Ingest #2`), its status and actions, then one line of metadata:
 * its asset, when and how long it ran, the event that triggered it (which
 * unfolds the event, context and outputs) and its ID.
 */
export function WorkflowHeader({
    workflow,
    onRefresh,
    onCancel,
    onOpenAsset,
}: {
    workflow: WorkflowDetail;
    onRefresh: () => Promise<unknown>;
    onCancel: () => Promise<void>;
    onOpenAsset?: () => void;
}) {
    const {t} = useTranslation();
    const [expanded, setExpanded] = useState(false);
    const hasDetails =
        !!workflow.event ||
        hasData(workflow.context) ||
        hasData(workflow.outputs);
    const toggle = () => setExpanded(e => !e);
    const duration = formatJobDuration(workflow.duration);

    const meta: ReactNode[] = [];
    if (workflow.asset) {
        const name = workflow.asset.name || t('nav.asset', 'Asset');
        meta.push(
            <Meta
                key="asset"
                label={t('workflow.field.asset', 'Asset')}
                icon={<ImageIcon />}
            >
                {onOpenAsset ? (
                    <button
                        type="button"
                        data-testid="workflow-asset"
                        className="max-w-[40ch] cursor-pointer truncate font-medium text-foreground hover:underline"
                        onClick={onOpenAsset}
                    >
                        {name}
                    </button>
                ) : (
                    <span className="max-w-[40ch] truncate">{name}</span>
                )}
            </Meta>
        );
    }
    meta.push(
        <Meta
            key="start"
            label={t('workflow.field.started_at', 'Started at')}
            icon={<ClockIcon />}
        >
            <RelativeDate date={workflow.startedAt} />
            {workflow.endedAt ? (
                <>
                    {' → '}
                    <RelativeDate date={workflow.endedAt} />
                </>
            ) : null}
        </Meta>
    );
    if (duration) {
        meta.push(
            <Meta
                key="duration"
                label={t('workflow.field.duration', 'Duration')}
                icon={<TimerIcon />}
            >
                <span className="tabular-nums">{duration}</span>
            </Meta>
        );
    }
    if (workflow.event) {
        meta.push(
            <Meta
                key="event"
                label={t('workflow.field.event', 'Event')}
                icon={<ZapIcon />}
            >
                <button
                    type="button"
                    className="cursor-pointer font-mono hover:underline"
                    onClick={toggle}
                >
                    {workflow.event.name}
                </button>
            </Meta>
        );
    }

    return (
        <div className="shrink-0 border-b">
            <DialogHeader className="gap-1.5 px-4 pt-3 pr-12 pb-3">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                    {hasDetails ? (
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            className="-ml-1"
                            onClick={toggle}
                            aria-expanded={expanded}
                            aria-label={
                                expanded
                                    ? t('workflow.hide_details', 'Hide details')
                                    : t('workflow.show_details', 'Show details')
                            }
                        >
                            <ChevronDownIcon
                                className={cn(
                                    'transition-transform',
                                    expanded && 'rotate-180'
                                )}
                            />
                        </Button>
                    ) : null}
                    <DialogTitle
                        className="min-w-0 truncate"
                        title={workflow.name}
                    >
                        {workflowTitle(workflow, t)}
                    </DialogTitle>
                    {workflowStatusBadge(workflow.status, t)}
                    <div className="ml-auto flex items-center gap-2">
                        <Button variant="ghost" size="sm" onClick={onRefresh}>
                            <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                        </Button>
                        {canCancelWorkflow(workflow) ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={onCancel}
                            >
                                <BanIcon /> {t('common.cancel', 'Cancel')}
                            </Button>
                        ) : null}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    {meta.map((item, i) => (
                        <Fragment key={i}>
                            {i > 0 ? <span aria-hidden>·</span> : null}
                            {item}
                        </Fragment>
                    ))}
                    <span className="ml-auto">
                        <CopiableText value={workflow.id} />
                    </span>
                </div>
            </DialogHeader>
            {expanded && hasDetails ? (
                <div
                    data-testid="workflow-details"
                    className="max-h-[40dvh] space-y-3 overflow-y-auto px-4 pb-4"
                >
                    {workflow.event ? (
                        <JsonSection
                            title={t(
                                'workflow.event_title',
                                'Event: {{name}}',
                                {
                                    name: workflow.event.name,
                                }
                            )}
                            data={workflow.event.inputs ?? {}}
                        />
                    ) : null}
                    {hasData(workflow.context) ? (
                        <JsonSection
                            title={t('workflow.field.context', 'Context')}
                            data={workflow.context}
                        />
                    ) : null}
                    {hasData(workflow.outputs) ? (
                        <JsonSection
                            title={t('workflow.field.outputs', 'Outputs')}
                            data={workflow.outputs}
                        />
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
