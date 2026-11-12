'use client';

import {useTranslation} from 'react-i18next';
import {CropIcon, ScanIcon, ScissorsIcon} from 'lucide-react';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {MediaClip} from './types';
import {formatClipRanges, hasClip} from './clip';

/**
 * Tells that a basket item is an extract: its time ranges, and whether it is
 * cropped. With `inherited`, the item has no extract of its own: the clip is
 * the region of interest of its asset.
 */
export function ClipBadge({
    clip,
    inherited = false,
    className,
}: {
    clip: MediaClip | null | undefined;
    inherited?: boolean;
    className?: string;
}) {
    const {t} = useTranslation();
    if (!clip || !hasClip(clip)) {
        return null;
    }
    const ranges = clip.timeRanges ?? [];
    const title = inherited
        ? t('basket.clip.badge_roi', 'Region of interest of the asset')
        : t('basket.clip.badge_extract', 'Extract of this basket item');

    return (
        <span
            data-testid="basket-clip-badge"
            data-inherited={inherited || undefined}
            title={title}
            className={cn(
                'pointer-events-none flex items-center gap-1 rounded bg-background/90 px-1.5 py-0.5 font-mono text-[11px] font-semibold shadow-sm',
                inherited && 'text-muted-foreground',
                className
            )}
        >
            {inherited ? (
                <ScanIcon className="size-3" aria-label={title} />
            ) : null}
            {ranges.length > 0 ? (
                <>
                    {inherited ? null : <ScissorsIcon className="size-3" />}
                    {formatClipRanges(ranges)}
                </>
            ) : null}
            {clip.crop ? (
                <CropIcon
                    className="size-3"
                    aria-label={t('basket.clip.badge_crop', 'Cropped')}
                />
            ) : null}
        </span>
    );
}
