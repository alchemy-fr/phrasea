'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {LayersIcon, Trash2Icon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {FileKindIcon, FileTypeChip} from '@/components/chips';
import {useDisplayPreferences} from '@/features/preferences/store';
import {cn} from '@/lib/utils/cn';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {AnalysisChip} from '@/features/assets/quarantine/AnalysisChip';
import {usePreview} from '@/features/assets/list/preview/PreviewProvider';
import {AudioPlayer} from '@/features/assets/player/AudioPlayer';
import {StoryThumb} from '@/features/assets/list/StoryThumb';

/**
 * Thumbnail of an asset (with animated preview on hover when available),
 * falling back to a file type icon. The image fills its box according to the
 * `thumbFit` display preference (whole image vs. cropped cover).
 *
 * Video and audio thumbnails (the thumbnail rendition of a sound is a sound,
 * drawn as a waveform) stay still, and play while hovered when the
 * `playOnHover` display preference is on; a story shows the carousel of its
 * items' thumbnails while hovered (see `StoryThumb`).
 *
 * With `previewOnHover`, hovering the file type chip opens the preview popover
 * (see `PreviewProvider`), anchored on the thumbnail; clicking the chip locks
 * the preview open.
 *
 * A file still being analyzed — or rejected by the analyzers — normally shows
 * its analysis state instead of the image; `ignoreAnalysis` renders the
 * thumbnail rendition anyway, for screens where the picture is what the user
 * has to look at (quarantine resolution, duplicate comparison).
 */
export function AssetThumb({
    asset,
    size,
    className,
    previewOnHover,
    ignoreAnalysis,
}: {
    asset: Asset;
    size?: number;
    className?: string;
    previewOnHover?: boolean;
    ignoreAnalysis?: boolean;
}) {
    const {t} = useTranslation();
    const [hover, setHover] = useState(false);
    const {thumbFit, playOnHover} = useDisplayPreferences();
    const preview = usePreview();
    const container = useRef<HTMLDivElement>(null);
    const thumb = asset.thumbnail?.file;
    const animated = asset.animatedThumbnail?.file;
    const source = asset.source;
    const pending =
        !ignoreAnalysis &&
        (source?.analysisPending || source?.accepted === false);

    const url = hover && animated?.url ? animated.url : thumb?.url;
    const fitClass = thumbFit === 'cover' ? 'object-cover' : 'object-contain';
    const thumbKind = getFileKind(
        hover && animated?.url ? animated.type : thumb?.type
    );
    const isVideoThumb = !!url && thumbKind === FileKind.Video;
    const isAudioThumb = !!url && thumbKind === FileKind.Audio;
    const storyCarousel = !!asset.storyCollection && !asset.deleted;
    const videoRef = useRef<HTMLVideoElement>(null);
    const playing = hover && playOnHover;

    // A video thumbnail only plays while hovered, when enabled
    useEffect(() => {
        const video = videoRef.current;
        if (!video) {
            return;
        }
        if (playing) {
            // jsdom has no `play()` implementation (returns undefined)
            video.play()?.catch(() => undefined);
        } else {
            video.pause();
            video.currentTime = 0;
        }
    }, [playing, url]);

    return (
        <div
            ref={container}
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
                        ref={videoRef}
                        src={url}
                        className={cn('size-full', fitClass)}
                        muted
                        loop
                        playsInline
                        preload="metadata"
                    />
                ) : isAudioThumb ? (
                    <AudioPlayer
                        src={url}
                        controls={false}
                        playing={playing}
                        height={Math.round((size ?? 200) * 0.4)}
                        className="size-full px-2"
                    />
                ) : (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={url}
                        alt={asset.name ?? ''}
                        loading="lazy"
                        decoding="async"
                        draggable={false}
                        className={cn('size-full', fitClass)}
                        style={size ? {maxHeight: size} : undefined}
                    />
                )
            ) : (
                <FileKindIcon
                    mimeType={source?.type}
                    className="size-1/3 opacity-60"
                />
            )}

            {storyCarousel ? (
                <StoryThumb
                    assetId={asset.id}
                    size={size ?? 200}
                    active={hover}
                />
            ) : null}

            <div className="pointer-events-none absolute right-1 bottom-1 z-10 flex items-center gap-1">
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
                    <span
                        data-testid="asset-type-chip"
                        className={cn(
                            'flex',
                            previewOnHover &&
                                'pointer-events-auto rounded ring-white/60 transition-shadow hover:ring-2'
                        )}
                        onMouseEnter={
                            previewOnHover
                                ? () =>
                                      container.current &&
                                      preview.onEnter(asset, container.current)
                                : undefined
                        }
                        onMouseLeave={
                            previewOnHover
                                ? () => preview.onLeave(asset)
                                : undefined
                        }
                        onClick={
                            previewOnHover
                                ? e => {
                                      e.stopPropagation();
                                      if (container.current) {
                                          preview.onLock(
                                              asset,
                                              container.current
                                          );
                                      }
                                  }
                                : undefined
                        }
                        onDoubleClick={
                            previewOnHover
                                ? e => e.stopPropagation()
                                : undefined
                        }
                    >
                        <FileTypeChip
                            mimeType={source.type}
                            extension={source.extension}
                        />
                    </span>
                ) : null}
            </div>
        </div>
    );
}
