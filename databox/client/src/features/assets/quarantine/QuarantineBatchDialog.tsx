'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {TFunction} from 'i18next';
import {useQueryClient} from '@tanstack/react-query';
import {AlertTriangleIcon, Trash2Icon} from 'lucide-react';
import type {
    Asset,
    DuplicateAsset,
    QuarantineBatchAction,
    QuarantineBatchEligibility,
    QuarantineBatchPrepareItem,
    QuarantineBatchResult,
    QuarantineBatchResultStatus,
} from '@/types/api';
import type {ModalProps} from '@alchemy/ui/modals/ModalProvider';
import {FormDialog} from '@alchemy/ui/modals/FormDialog';
import {TypeToConfirm} from '@alchemy/ui/ui/confirm';
import {RadioGroup, RadioGroupItem} from '@alchemy/ui/ui/controls';
import {SimpleSelect} from '@alchemy/ui/ui/select';
import {Alert, Badge} from '@alchemy/ui/ui/misc';
import {runQuarantineBatch} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {quarantineQueueKey, useQuarantineQueueStore} from './quarantineQueue';

type Row = {
    asset: Asset;
    /** `pending`: eligible, but its target is still to be chosen */
    status: 'ready' | 'pending' | 'ignored';
    reason?: string;
    /** Merge as a new version: the duplicates that can receive the file */
    targets: DuplicateAsset[];
};

type ReportEntry = {
    asset?: Asset;
    id: string;
    status: QuarantineBatchResultStatus;
    reason?: string;
};

type Props = ModalProps<QuarantineBatchResult> & {
    action: QuarantineBatchAction;
    /** The selected assets */
    assets: Asset[];
    /** Their eligibility, by asset ID */
    eligibility: Map<string, QuarantineBatchPrepareItem>;
    /** Called once the batch ran, with the IDs of the processed assets */
    onComplete?: (processedIds: string[]) => void;
};

export function quarantineReasonLabel(t: TFunction, reason?: string): string {
    switch (reason) {
        case 'not_found':
            return t('quarantine.batch.reason.not_found', 'No longer found');
        case 'not_quarantined':
            return t(
                'quarantine.batch.reason.not_quarantined',
                'No longer in quarantine'
            );
        case 'forbidden':
            return t('quarantine.batch.reason.forbidden', 'Not allowed');
        case 'no_duplicate':
            return t('quarantine.batch.reason.no_duplicate', 'No duplicate');
        case 'no_valid_target':
            return t(
                'quarantine.batch.reason.no_valid_target',
                'No duplicate can receive the file (another workspace, or not editable)'
            );
        case 'target_required':
            return t(
                'quarantine.batch.reason.target_required',
                'Target to choose'
            );
        case 'invalid_target':
            return t(
                'quarantine.batch.reason.invalid_target',
                'Invalid target'
            );
        default:
            return t('quarantine.batch.reason.error', 'Error');
    }
}

function assetLabel(asset: Asset | undefined, id: string): string {
    return asset?.name ?? asset?.source?.fileName ?? id;
}

/**
 * Confirmation, then result, of a batch action on the selected quarantined
 * assets. The assets the action does not apply to are ignored. Merging keeps
 * the values of the existing asset: only the fate of the incoming file is
 * asked, plus the target of each asset having several duplicates that can
 * receive it.
 */
export function QuarantineBatchDialog({
    open,
    onOpenChange,
    resolve,
    action,
    assets,
    eligibility,
    onComplete,
}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const markResolved = useQuarantineQueueStore(s => s.markResolved);
    const removeFromStore = useAssetStore(s => s.remove);
    const reloadAsset = useAssetStore(s => s.reloadAsset);
    const [addAsVersion, setAddAsVersion] = useState(true);
    const [chosen, setChosen] = useState<Record<string, string>>({});
    const [typed, setTyped] = useState('');
    const [report, setReport] = useState<{
        result: QuarantineBatchResult;
        entries: ReportEntry[];
    }>();

    const key: QuarantineBatchEligibility =
        action === 'merge'
            ? addAsVersion
                ? 'mergeAsVersion'
                : 'mergeDiscard'
            : action;

    const rows = useMemo<Row[]>(
        () =>
            assets.map(asset => {
                const item = eligibility.get(asset.id);
                if (!item?.[key]) {
                    return {
                        asset,
                        status: 'ignored',
                        reason: item?.reasons?.[key] ?? 'not_found',
                        targets: [],
                    };
                }
                const targets =
                    key === 'mergeAsVersion'
                        ? item.duplicates.filter(d => d.validTarget)
                        : [];
                const pending =
                    targets.length > 1 &&
                    !targets.some(d => d.asset.id === chosen[asset.id]);

                return {asset, status: pending ? 'pending' : 'ready', targets};
            }),
        [assets, eligibility, key, chosen]
    );
    const ready = rows.filter(r => r.status === 'ready');
    const pending = rows.filter(r => r.status === 'pending');
    const ignored = rows.filter(r => r.status === 'ignored');
    const withChoice = rows.filter(r => r.targets.length > 1);

    const confirmWord = t('asset.delete.confirm_word', 'Delete');
    const canSubmit =
        ready.length > 0 &&
        (action !== 'delete' || typed.trim() === confirmWord);

    const title = {
        bypass: t('quarantine.bypass', 'Bypass'),
        merge: t('quarantine.merge', 'Merge duplicates'),
        trash: t('quarantine.trash', 'Move to trash'),
        delete: t('asset.actions.delete_permanently', 'Delete permanently'),
    }[action];

    const submit = async () => {
        const result = await runQuarantineBatch({
            action,
            items: ready.map(r => ({
                id: r.asset.id,
                ...(r.targets.length > 1 ? {targetId: chosen[r.asset.id]} : {}),
            })),
            ...(action === 'merge' ? {addAsVersion} : {}),
        });

        const processed = result.results
            .filter(r => r.status === 'processed')
            .map(r => r.id);
        if (processed.length > 0) {
            markResolved(processed);
            void queryClient.invalidateQueries({queryKey: quarantineQueueKey});
            if (action === 'bypass') {
                processed.forEach(id => void reloadAsset(id));
            } else {
                removeFromStore(processed);
            }
        }

        const byId = new Map(assets.map(a => [a.id, a]));
        setReport({
            result,
            entries: [
                ...result.results.map(r => ({...r, asset: byId.get(r.id)})),
                // Left out before running: reported as ignored too
                ...[...pending, ...ignored].map(r => ({
                    asset: r.asset,
                    id: r.asset.id,
                    status: 'skipped' as const,
                    reason:
                        r.status === 'pending' ? 'target_required' : r.reason,
                })),
            ],
        });
        onComplete?.(processed);

        // Stay open on the result
        return false;
    };

    if (report) {
        const skipped = report.entries.filter(e => e.status === 'skipped');
        const failed = report.entries.filter(e => e.status === 'failed');

        return (
            <FormDialog
                open={open}
                onOpenChange={onOpenChange}
                title={title}
                hideCancel
                submitLabel={t('common.close', 'Close')}
                bodyClassName="space-y-3"
                onSubmit={() => resolve?.(report.result)}
            >
                <div
                    className="flex flex-wrap gap-2"
                    data-testid="quarantine-batch-report"
                >
                    <Badge variant="success">
                        {t('quarantine.batch.report.processed', {
                            count: report.result.processed,
                            defaultValue_one: '{{count}} processed',
                            defaultValue_other: '{{count}} processed',
                        })}
                    </Badge>
                    <Badge variant="muted">
                        {t('quarantine.batch.report.skipped', {
                            count: skipped.length,
                            defaultValue_one: '{{count}} ignored',
                            defaultValue_other: '{{count}} ignored',
                        })}
                    </Badge>
                    <Badge
                        variant={failed.length > 0 ? 'destructive' : 'muted'}
                    >
                        {t('quarantine.batch.report.failed', {
                            count: failed.length,
                            defaultValue_one: '{{count}} failed',
                            defaultValue_other: '{{count}} failed',
                        })}
                    </Badge>
                </div>
                {failed.length + skipped.length > 0 ? (
                    <ul
                        className="max-h-72 space-y-1 overflow-y-auto text-sm"
                        data-testid="quarantine-batch-report-list"
                    >
                        {[...failed, ...skipped].map(e => (
                            <li
                                key={e.id}
                                className="flex items-center gap-2"
                                data-testid="quarantine-batch-report-item"
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {assetLabel(e.asset, e.id)}
                                </span>
                                <span
                                    className={
                                        e.status === 'failed'
                                            ? 'text-destructive'
                                            : 'text-muted-foreground'
                                    }
                                >
                                    {quarantineReasonLabel(t, e.reason)}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : null}
            </FormDialog>
        );
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size={withChoice.length > 0 ? 'lg' : 'sm'}
            title={title}
            description={
                action === 'merge'
                    ? t(
                          'quarantine.batch.merge_help',
                          'The values of the existing assets are kept.'
                      )
                    : undefined
            }
            submitLabel={title}
            submitIcon={
                action === 'trash' || action === 'delete' ? (
                    <Trash2Icon />
                ) : undefined
            }
            submitVariant={action === 'delete' ? 'destructive' : undefined}
            canSubmit={canSubmit}
            bodyClassName="space-y-3"
            onSubmit={submit}
        >
            <p className="text-sm" data-testid="quarantine-batch-summary">
                {[
                    t('quarantine.batch.summary.processed', {
                        count: ready.length,
                        defaultValue_one: '{{count}} asset will be processed',
                        defaultValue_other:
                            '{{count}} assets will be processed',
                    }),
                    t('quarantine.batch.summary.ignored', {
                        count: ignored.length,
                        defaultValue_one: '{{count}} ignored',
                        defaultValue_other: '{{count}} ignored',
                    }),
                    ...(pending.length > 0
                        ? [
                              t('quarantine.batch.summary.pending', {
                                  count: pending.length,
                                  defaultValue_one:
                                      '{{count}} waiting for its target',
                                  defaultValue_other:
                                      '{{count}} waiting for their target',
                              }),
                          ]
                        : []),
                ].join(', ')}
            </p>

            {action === 'merge' ? (
                <RadioGroup
                    value={addAsVersion ? 'version' : 'discard'}
                    onValueChange={v => setAddAsVersion(v === 'version')}
                    className="space-y-2"
                >
                    <label className="flex items-center gap-2 text-sm">
                        <RadioGroupItem value="version" />{' '}
                        {t(
                            'quarantine.add_as_version',
                            'Keep the incoming file as a new version of the existing asset'
                        )}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <RadioGroupItem value="discard" />{' '}
                        {t(
                            'quarantine.discard_incoming',
                            'Discard the incoming file'
                        )}
                    </label>
                </RadioGroup>
            ) : null}

            {withChoice.length > 0 ? (
                <section className="space-y-2">
                    <p className="text-xs font-semibold text-muted-foreground uppercase">
                        {t(
                            'quarantine.batch.targets',
                            'Existing asset receiving each incoming file'
                        )}
                    </p>
                    <ul className="max-h-72 space-y-2 overflow-y-auto">
                        {withChoice.map(r => (
                            <li
                                key={r.asset.id}
                                className="flex items-center gap-2"
                                data-testid="quarantine-batch-target"
                            >
                                <span className="size-8 shrink-0 overflow-hidden rounded bg-media-bg">
                                    <AssetThumb
                                        asset={r.asset}
                                        size={32}
                                        ignoreAnalysis
                                    />
                                </span>
                                <span className="min-w-0 flex-1 truncate text-sm">
                                    {assetLabel(r.asset, r.asset.id)}
                                </span>
                                <SimpleSelect
                                    className="w-64"
                                    value={chosen[r.asset.id]}
                                    placeholder={t(
                                        'quarantine.batch.target_placeholder',
                                        'Choose a target'
                                    )}
                                    onValueChange={v =>
                                        setChosen(c => ({
                                            ...c,
                                            [r.asset.id]: v,
                                        }))
                                    }
                                    options={r.targets.map(d => ({
                                        value: d.asset.id,
                                        searchText: assetLabel(
                                            d.asset,
                                            d.asset.id
                                        ),
                                        label: (
                                            <>
                                                <span className="size-6 shrink-0 overflow-hidden rounded bg-media-bg">
                                                    <AssetThumb
                                                        asset={d.asset}
                                                        size={24}
                                                        ignoreAnalysis
                                                    />
                                                </span>
                                                <span className="truncate">
                                                    {assetLabel(
                                                        d.asset,
                                                        d.asset.id
                                                    )}
                                                </span>
                                            </>
                                        ),
                                    }))}
                                />
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {ignored.length > 0 ? (
                <details
                    className="text-sm"
                    data-testid="quarantine-batch-ignored"
                >
                    <summary className="cursor-pointer text-muted-foreground">
                        {t('quarantine.batch.ignored_list', {
                            count: ignored.length,
                            defaultValue_one: '{{count}} ignored asset',
                            defaultValue_other: '{{count}} ignored assets',
                        })}
                    </summary>
                    <ul className="mt-1 max-h-48 space-y-1 overflow-y-auto">
                        {ignored.map(r => (
                            <li key={r.asset.id} className="flex gap-2">
                                <span className="min-w-0 flex-1 truncate">
                                    {assetLabel(r.asset, r.asset.id)}
                                </span>
                                <span className="text-muted-foreground">
                                    {quarantineReasonLabel(t, r.reason)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </details>
            ) : null}

            {action === 'delete' ? (
                <>
                    <Alert variant="destructive" icon={<AlertTriangleIcon />}>
                        {t(
                            'quarantine.batch.delete_warning',
                            'The assets will be deleted for good, without going through the trash.'
                        )}
                    </Alert>
                    <TypeToConfirm
                        text={confirmWord}
                        value={typed}
                        onChange={setTyped}
                    />
                </>
            ) : null}
        </FormDialog>
    );
}
