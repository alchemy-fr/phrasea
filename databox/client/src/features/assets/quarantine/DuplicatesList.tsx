'use client';

import {useTranslation} from 'react-i18next';
import type {DuplicateAsset} from '@/types/api';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {formatDateTime} from '@/lib/utils/format';
import {useAssetOpener} from '@/features/assets/useAssetOpener';

/**
 * Existing assets an analyzer flagged as duplicates of the quarantined one.
 */
export function DuplicatesList({duplicates}: {duplicates: DuplicateAsset[]}) {
    const {t, i18n} = useTranslation();
    const openAsset = useAssetOpener();

    return (
        <div>
            <p className="mb-1 text-xs font-semibold text-muted-foreground uppercase">
                {t('quarantine.duplicates', 'Possible duplicates')}
            </p>
            <ul className="space-y-1">
                {duplicates.map(d => (
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
                                ignoreAnalysis
                            />
                        </button>
                        <span className="min-w-0 flex-1 truncate">
                            {d.asset.name}
                        </span>
                        <span className="shrink-0 whitespace-nowrap tabular-nums text-muted-foreground">
                            {formatDateTime(
                                d.asset.createdAt,
                                'short',
                                i18n.language
                            )}
                        </span>
                        <span className="w-40 shrink-0 truncate text-right text-muted-foreground">
                            {d.analyzers.join(', ')}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
