'use client';

import {useEffect, useState} from 'react';
import dynamic from 'next/dynamic';
import {useTranslation} from 'react-i18next';
import type {Asset} from '@/types/api';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {cn} from '@/lib/utils/cn';
import {FileKindIcon} from '@/components/ui/file-icon';
import {Spinner} from '@/components/ui/loader';
import {
    contentClick,
    getContentTracking,
    useContentImpression,
} from '@/lib/analytics/useContentTracking';
import {AudioPlayer} from './AudioPlayer';
import {ZoomableImage} from './ZoomableImage';
import {loadSubtitleTracks, type SubtitleTrack} from './subtitles';

function PlayerLoader() {
    return (
        <div className="flex size-full items-center justify-center">
            <Spinner />
        </div>
    );
}

// pdf.js is loaded with the first PDF displayed, in the browser only
const PdfViewer = dynamic(() => import('./PdfViewer').then(m => m.PdfViewer), {
    ssr: false,
    loading: PlayerLoader,
});

// Video.js is loaded with the first video played, in the browser only
const VideoJsPlayer = dynamic(
    () => import('./VideoJsPlayer').then(m => m.VideoJsPlayer),
    {ssr: false, loading: PlayerLoader}
);

type Props = {
    asset: Asset;
    /** `zoom` enables zoom/pan on images */
    fit?: 'contain' | 'zoom';
    /** Offers the WebVTT subtitles of a video */
    subtitles?: boolean;
    autoPlay?: boolean;
    className?: string;
};

/** Subtitle tracks of the asset, loaded once its video is shown */
function useSubtitles(
    asset: Asset,
    enabled: boolean
): SubtitleTrack[] | undefined {
    const {i18n} = useTranslation();
    const [tracks, setTracks] = useState<SubtitleTrack[]>();
    const links = enabled ? asset.webVTTLinks : undefined;

    useEffect(() => {
        setTracks(undefined);
        if (!links?.length) {
            return;
        }
        const controller = new AbortController();
        loadSubtitleTracks(links, i18n.language, controller.signal).then(
            t => {
                if (!controller.signal.aborted) {
                    setTracks(t);
                }
            },
            () => undefined
        );

        return () => controller.abort();
    }, [links, i18n.language]);

    return tracks;
}

/**
 * Plays the preview of an asset with the player of its type: image, video
 * (with its subtitles), audio, PDF; the icon of the type otherwise.
 */
export function AssetPlayer({
    asset,
    fit = 'zoom',
    subtitles = true,
    autoPlay,
    className,
}: Props) {
    const url = asset.previewUrl;
    const type = asset.previewType || asset.mimeType;
    const kind = getFileKind(type);
    const tracking = getContentTracking(asset);
    const title = asset.title || asset.originalName || '';
    const tracks = useSubtitles(asset, subtitles && kind === FileKind.Video);
    useContentImpression(asset, url ? type : undefined);
    const onContentClick = () => contentClick(tracking);

    if (!url) {
        return (
            <div
                className={cn(
                    'flex size-full items-center justify-center',
                    className
                )}
            >
                <FileKindIcon mimeType={asset.mimeType} className="size-20" />
            </div>
        );
    }

    switch (kind) {
        case FileKind.Image:
            return fit === 'zoom' ? (
                <ZoomableImage
                    // The view (zoom, rotation) belongs to one image
                    key={asset.id}
                    src={url}
                    alt={title}
                    className={className}
                    onContentClick={onContentClick}
                />
            ) : (
                <div
                    className={cn(
                        'flex size-full items-center justify-center',
                        className
                    )}
                >
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                        src={url}
                        alt={title}
                        className="max-h-full max-w-full object-contain"
                        draggable={false}
                        onClick={onContentClick}
                    />
                </div>
            );
        case FileKind.Video:
            return (
                <VideoJsPlayer
                    // The playback belongs to one video
                    key={asset.id}
                    src={url}
                    type={type}
                    poster={asset.posterUrl ?? undefined}
                    autoPlay={autoPlay}
                    className={className}
                    tracking={tracking}
                    subtitles={tracks}
                />
            );
        case FileKind.Audio:
            return (
                <div
                    className={cn(
                        'flex size-full items-center justify-center',
                        className
                    )}
                >
                    <AudioPlayer key={asset.id} src={url} autoPlay={autoPlay} />
                </div>
            );
        case FileKind.Document:
            if (type === 'application/pdf') {
                return (
                    <PdfViewer
                        // The view (page, zoom) belongs to one document
                        key={asset.id}
                        src={url}
                        className={className}
                        onContentClick={onContentClick}
                    />
                );
            }
    }

    return (
        <div
            className={cn(
                'flex size-full items-center justify-center',
                className
            )}
        >
            <FileKindIcon mimeType={type} className="size-20" />
        </div>
    );
}
