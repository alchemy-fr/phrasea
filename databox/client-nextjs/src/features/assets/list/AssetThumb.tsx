'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {LayersIcon, Trash2Icon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {FileKindIcon, FileTypeChip} from '@/components/chips';
import {useDisplayPreferences} from '@/features/preferences/store';
import {cn} from '@/lib/utils/cn';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {AnalysisChip} from '@/features/assets/quarantine/AnalysisChip';

/**
 * Thumbnail of an asset (with animated preview on hover when available),
 * falling back to a file type icon.
 */
export function AssetThumb({
    asset,
    size,
    className,
}: {
    asset: Asset;
    size?: number;
    className?: string;
}) {
    const {t} = useTranslation();
    const [hover, setHover] = useState(false);
    const {displayPreview} = useDisplayPreferences();
    const thumb = asset.thumbnail?.file;
    const animated = asset.animatedThumbnail?.file;
    const source = asset.source;
    const pending = source?.analysisPending || source?.accepted === false;

    const url = hover && animated?.url ? animated.url : thumb?.url;
    const isVideoThumb =
        url &&
        getFileKind(hover && animated?.url ? animated.type : thumb?.type) ===
            FileKind.Video;

    return (
        <div
            className={cn(
                'relative flex size-full items-center justify-center overflow-hidden',
                className
            )}
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
        >
            {pending && source ? (
                <div className="flex flex-col items-center gap-2">
                    <FileKindIcon mimeType={source.type} />
                    <AnalysisChip file={source} />
                </div>
            ) : url ? (
                isVideoThumb ? (
                    <video
                        src={url}
                        className="size-full object-contain"
                        muted
                        loop
                        autoPlay
                        playsInline
                    />
                ) : (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={url}
                        alt={asset.name ?? ''}
                        loading="lazy"
                        decoding="async"
                        draggable={false}
                        className="size-full object-contain"
                        style={size ? {maxHeight: size} : undefined}
                    />
                )
            ) : (
                <FileKindIcon
                    mimeType={source?.type}
                    className="size-1/3 opacity-60"
                />
            )}

            <div className="pointer-events-none absolute right-1 bottom-1 flex items-center gap-1">
                {asset.storyCollection ? (
                    <span
                        className="flex items-center gap-0.5 rounded bg-black/60 px-1 py-0.5 text-[10px] font-semibold text-white"
                        title={t('asset.story', 'Story')}
                    >
                        <LayersIcon className="size-3" />{' '}
                        {t('asset.story', 'Story')}
                    </span>
                ) : null}
                {asset.deleted ? (
                    <span className="flex items-center gap-0.5 rounded bg-destructive/90 px-1 py-0.5 text-[10px] font-semibold text-white">
                        <Trash2Icon className="size-3" />{' '}
                        {t('asset.deleted', 'Deleted')}
                    </span>
                ) : null}
                {source && url ? (
                    <FileTypeChip
                        mimeType={source.type}
                        extension={source.extension}
                    />
                ) : null}
            </div>
            {displayPreview ? null : null}
        </div>
    );
}
