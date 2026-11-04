'use client';

import {useTranslation} from 'react-i18next';
import {CropIcon, ScissorsIcon} from 'lucide-react';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {BasketClip} from './types';
import {formatClipRange} from './clip';

/**
 * Tells that a basket item is an extract: its time range, and whether it is
 * cropped.
 */
export function ClipBadge({
    clip,
    className,
}: {
    clip: BasketClip | undefined;
    className?: string;
}) {
    const {t} = useTranslation();
    if (!clip?.timeRange && !clip?.crop) {
        return null;
    }

    return (
        <span
            data-testid="basket-clip-badge"
            className={cn(
                'pointer-events-none flex items-center gap-1 rounded bg-background/90 px-1.5 py-0.5 font-mono text-[11px] font-semibold shadow-sm',
                className
            )}
        >
            {clip.timeRange ? (
                <>
                    <ScissorsIcon className="size-3" />
                    {formatClipRange(clip.timeRange)}
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
