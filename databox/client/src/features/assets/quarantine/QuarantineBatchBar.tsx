'use client';

import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {
    CopyCheckIcon,
    RotateCwIcon,
    ShieldCheckIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import type {QuarantineBatchAction} from '@/types/api';
import {Button} from '@alchemy/ui/ui/button';
import {Badge} from '@alchemy/ui/ui/misc';
import type {QuarantineEligibilityCounts} from './useQuarantineEligibility';

/**
 * The batch actions on the selected assets of the quarantine queue, each with
 * how many of them it applies to. Merging counts the assets that can be
 * added as a new version, the default mode, including those whose target is
 * still to be chosen. When the eligibility could not be read, the counts
 * cover the assets known so far only: the bar tells so and offers to retry.
 */
export function QuarantineBatchBar({
    total,
    counts,
    loading,
    failed = false,
    onRetry,
    onClear,
    onRun,
}: {
    total: number;
    counts: QuarantineEligibilityCounts;
    loading: boolean;
    failed?: boolean;
    onRetry?: () => void;
    onClear: () => void;
    onRun: (action: QuarantineBatchAction) => void;
}) {
    const {t} = useTranslation();

    const actions: {
        action: QuarantineBatchAction;
        eligible: number;
        icon: ReactNode;
        label: string;
        destructive?: boolean;
    }[] = [
        {
            action: 'bypass',
            eligible: counts.bypass,
            icon: <ShieldCheckIcon />,
            label: t('quarantine.bypass', 'Bypass'),
        },
        {
            action: 'merge',
            eligible: counts.mergeAsVersion,
            icon: <CopyCheckIcon />,
            label: t('quarantine.merge', 'Merge duplicates'),
        },
        {
            action: 'trash',
            eligible: counts.trash,
            icon: <Trash2Icon />,
            label: t('quarantine.trash', 'Move to trash'),
        },
        {
            action: 'delete',
            eligible: counts.delete,
            icon: <Trash2Icon />,
            label: t('asset.actions.delete_permanently', 'Delete permanently'),
            destructive: true,
        },
    ];

    return (
        <div
            className="flex shrink-0 flex-wrap items-center gap-2 border-b bg-muted/40 px-3 py-2"
            data-testid="quarantine-batch-bar"
        >
            <Badge data-testid="quarantine-batch-selected">
                {t('quarantine.batch.selected', {
                    count: total,
                    defaultValue_one: '{{count}} selected',
                    defaultValue_other: '{{count}} selected',
                })}
            </Badge>
            <Button size="sm" variant="ghost" onClick={onClear}>
                <XIcon /> {t('quarantine.batch.clear', 'Deselect')}
            </Button>
            <div className="flex-1" />
            {failed ? (
                <span
                    className="flex items-center gap-1 text-xs text-destructive"
                    data-testid="quarantine-batch-eligibility-failed"
                    role="alert"
                >
                    {t(
                        'quarantine.batch.eligibility_failed',
                        'The actions available to the selection could not be checked'
                    )}
                    {onRetry ? (
                        <Button size="sm" variant="ghost" onClick={onRetry}>
                            <RotateCwIcon /> {t('common.retry', 'Retry')}
                        </Button>
                    ) : null}
                </span>
            ) : null}
            {actions.map(a => (
                <Button
                    key={a.action}
                    size="sm"
                    variant={a.destructive ? 'destructive' : 'outline'}
                    data-testid={`quarantine-batch-${a.action}`}
                    disabled={loading || a.eligible === 0}
                    onClick={() => onRun(a.action)}
                >
                    {a.icon}
                    {`${a.label} (${
                        loading
                            ? '…'
                            : t(
                                  'quarantine.batch.count',
                                  '{{eligible}} of {{total}}',
                                  {eligible: a.eligible, total}
                              )
                    })`}
                </Button>
            ))}
        </div>
    );
}
