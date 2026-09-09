'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    AlertTriangleIcon,
    ChevronDownIcon,
    CopyCheckIcon,
    ShieldCheckIcon,
    Trash2Icon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import {Alert, Badge} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {bypassQuarantine, getAssetDuplicates} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {useModals} from '@/components/modals/ModalProvider';
import {DeleteAssetsDialog} from '@/features/assets/actions/DeleteAssetsDialog';
import {MergeDuplicatesDialog} from './MergeDuplicatesDialog';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {formatDateTime} from '@/lib/utils/format';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {cn} from '@/lib/utils/cn';

type AnalyzerResult = {
    analyzer?: string;
    level?: number | string;
    message?: string;
    passed?: boolean;
    accepted?: boolean;
    [k: string]: unknown;
};

export const severityLabels: Record<number, string> = {
    0: 'debug',
    1: 'info',
    2: 'notice',
    3: 'warning',
    4: 'error',
    5: 'critical',
};

/**
 * Analysis report + quarantine actions for a quarantined asset.
 */
export function QuarantineBanner({
    asset,
    compact,
}: {
    asset: Asset;
    compact?: boolean;
}) {
    const {t, i18n} = useTranslation();
    const {openModal} = useModals();
    const update = useAssetStore(s => s.update);
    const openAsset = useAssetOpener();
    const [expanded, setExpanded] = useState(!compact);
    const [bypassing, setBypassing] = useState(false);
    const analysis = (asset.source?.analysis ?? {}) as Record<
        string,
        AnalyzerResult | unknown
    >;
    const entries = Object.entries(analysis).filter(
        ([k]) => !['accepted', 'status', 'reason'].includes(k)
    );

    const duplicates = useQuery({
        queryKey: ['asset-duplicates', asset.id],
        queryFn: () => getAssetDuplicates(asset.id),
        enabled: expanded,
    });

    const bypass = async () => {
        setBypassing(true);
        try {
            const updated = await bypassQuarantine(asset.id);
            update(updated);
            toast.success(t('quarantine.bypassed', 'Asset accepted'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setBypassing(false);
        }
    };

    return (
        <Alert
            variant="destructive"
            className="mb-2"
            icon={<AlertTriangleIcon />}
        >
            <div className="flex items-center gap-2">
                <span className="font-medium">
                    {t('quarantine.title', 'This asset is quarantined')}
                </span>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    className="ml-auto text-inherit"
                    onClick={() => setExpanded(e => !e)}
                >
                    <ChevronDownIcon
                        className={cn(
                            'transition-transform',
                            expanded && 'rotate-180'
                        )}
                    />
                </Button>
            </div>
            {expanded ? (
                <div
                    className="mt-2 space-y-3 text-foreground"
                    onClick={e => e.stopPropagation()}
                    onDoubleClick={e => e.stopPropagation()}
                >
                    {entries.length > 0 ? (
                        <ul className="space-y-1 text-xs">
                            {entries.map(([name, raw]) => {
                                const r = (raw ?? {}) as AnalyzerResult;
                                const level =
                                    typeof r.level === 'number'
                                        ? (severityLabels[r.level] ??
                                          String(r.level))
                                        : (r.level as string | undefined);

                                return (
                                    <li
                                        key={name}
                                        className="flex items-start gap-2 rounded bg-background/60 px-2 py-1"
                                    >
                                        <Badge
                                            variant={
                                                r.passed === false ||
                                                r.accepted === false
                                                    ? 'destructive'
                                                    : 'success'
                                            }
                                        >
                                            {name}
                                        </Badge>
                                        {level ? (
                                            <span className="text-muted-foreground uppercase">
                                                {level}
                                            </span>
                                        ) : null}
                                        <span className="flex-1">
                                            {r.message ??
                                                (typeof raw === 'object'
                                                    ? JSON.stringify(raw)
                                                    : String(raw))}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : null}
                    {duplicates.data && duplicates.data.length > 0 ? (
                        <div>
                            <p className="mb-1 text-xs font-semibold uppercase text-muted-foreground">
                                {t(
                                    'quarantine.duplicates',
                                    'Possible duplicates'
                                )}
                            </p>
                            <ul className="space-y-1">
                                {duplicates.data.map(d => (
                                    <li
                                        key={d.asset.id}
                                        className="flex items-center gap-2 rounded bg-background/60 p-1 text-xs"
                                    >
                                        <button
                                            type="button"
                                            className="size-10 shrink-0 overflow-hidden rounded bg-media-bg"
                                            onClick={() => openAsset(d.asset)}
                                        >
                                            <AssetThumb
                                                asset={d.asset}
                                                size={40}
                                            />
                                        </button>
                                        <span className="min-w-0 flex-1 truncate">
                                            {d.asset.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatDateTime(
                                                d.asset.createdAt,
                                                'short',
                                                i18n.language
                                            )}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {d.analyzers.join(', ')}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={bypass}
                            loading={bypassing}
                        >
                            <ShieldCheckIcon />{' '}
                            {t('quarantine.bypass', 'Bypass')}
                        </Button>
                        {duplicates.data && duplicates.data.length > 0 ? (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    openModal(MergeDuplicatesDialog, {
                                        asset,
                                        duplicates: duplicates.data,
                                    })
                                }
                            >
                                <CopyCheckIcon />{' '}
                                {t('quarantine.merge', 'Merge duplicates')}
                            </Button>
                        ) : null}
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                openModal(DeleteAssetsDialog, {assets: [asset]})
                            }
                        >
                            <Trash2Icon />{' '}
                            {t('quarantine.trash', 'Move to trash')}
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() =>
                                openModal(DeleteAssetsDialog, {
                                    assets: [asset],
                                    hardDelete: true,
                                })
                            }
                        >
                            <Trash2Icon />{' '}
                            {t(
                                'asset.actions.delete_permanently',
                                'Delete permanently'
                            )}
                        </Button>
                    </div>
                </div>
            ) : null}
        </Alert>
    );
}
