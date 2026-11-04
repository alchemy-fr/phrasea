'use client';

import {useState} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {AlertTriangleIcon, ChevronDownIcon, WrenchIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {Alert} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {getAssetDuplicates} from '@/lib/api/assets';
import {routes} from '@/lib/routes';
import {AnalysisReport} from './AnalysisReport';
import {fileAnalysis} from './analysis';
import {DuplicatesList} from './DuplicatesList';
import {QuarantineActions} from './QuarantineActions';
import {cn} from '@/lib/utils/cn';

/**
 * Analysis report + quarantine actions for a quarantined asset, wherever it
 * shows up (results list, viewer). The "Resolve" link opens the dedicated
 * quarantine screen, focused on this asset.
 */
export function QuarantineBanner({
    asset,
    compact,
}: {
    asset: Asset;
    compact?: boolean;
}) {
    const {t} = useTranslation();
    const [expanded, setExpanded] = useState(!compact);

    const duplicates = useQuery({
        queryKey: ['asset-duplicates', asset.id],
        queryFn: () => getAssetDuplicates(asset.id),
        enabled: expanded,
    });

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
                    size="sm"
                    variant="outline"
                    className="ml-auto"
                    data-testid="quarantine-resolve-link"
                    asChild
                >
                    <Link
                        href={routes.quarantine(asset.id)}
                        onClick={e => e.stopPropagation()}
                    >
                        <WrenchIcon /> {t('quarantine.resolve', 'Resolve')}
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    className="text-inherit"
                    aria-label={t('quarantine.details', 'Analysis details')}
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
                    <AnalysisReport analysis={fileAnalysis(asset.source)} />
                    {duplicates.data && duplicates.data.length > 0 ? (
                        <DuplicatesList duplicates={duplicates.data} />
                    ) : null}
                    <QuarantineActions
                        asset={asset}
                        duplicates={duplicates.data}
                    />
                </div>
            ) : null}
        </Alert>
    );
}
