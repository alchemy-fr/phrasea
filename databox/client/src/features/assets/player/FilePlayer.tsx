'use client';

import {type CSSProperties, useEffect, useRef, useState} from 'react';
import dynamic from 'next/dynamic';
import type {ApiFile, ClipCrop, ClipTimeRange, MediaClip} from '@/types/api';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {FileKindIcon} from '@/components/chips';
import {AnalysisChip} from '@/features/assets/quarantine/AnalysisChip';
import {cn} from '@/lib/utils/cn';
import {AudioPlayer} from '@/features/assets/player/AudioPlayer';
import {ZoomableImage} from '@/features/assets/player/ZoomableImage';
import {scanForMedia} from '@/lib/analytics/matomo';
import {
    contentClick,
    type ContentTracking,
} from '@/lib/analytics/useContentTracking';
import {clipForFile} from '@/features/clip/clip';
import {ClipFrame} from '@/features/clip/ClipFrame';
import {useClipPlayback} from '@/features/clip/useClipPlayback';
import {CropOverlay} from '@/features/assets/crop/CropOverlay';
import {Spinner} from '@/components/ui/loader';
import type {SubtitleTrack} from '@/features/assets/player/subtitles';

// pdf.js is loaded with the first PDF displayed, in the browser only
const PdfViewer = dynamic(
    () => import('@/features/assets/player/PdfViewer').then(m => m.PdfViewer),
    {
        ssr: false,
        loading: () => (
            <div className="flex size-full items-center justify-center">
                <Spinner />
            </div>
        ),
    }
);

// Video.js is loaded with the first video played with controls, in the
// browser only
const VideoJsPlayer = dynamic(
    () =>
        import('@/features/assets/player/VideoJsPlayer').then(
            m => m.VideoJsPlayer
        ),
    {
        ssr: false,
        loading: () => (
            <div className="flex size-full items-center justify-center">
                <Spinner />
            </div>
        ),
    }
);

export type FilePlayerProps = {
    file: ApiFile;
    title?: string;
    autoPlay?: boolean;
    controls?: boolean;
    /**
     * `contain` keeps the whole media visible; `zoom` enables zoom/pan and
     * the annotations (viewer)
     */
    fit?: 'contain' | 'zoom';
    className?: string;
    onInteraction?: () => void;
    /**
     * The asset displayed, for Matomo: clicks on an image or a PDF, video
     * playback (MediaAnalytics)
     */
    tracking?: ContentTracking;
    /**
     * Region of interest of the asset, or extract of the basket item
     * displayed: bounds the playback and crops the media, for the parts that
     * apply to the file
     */
    clip?: MediaClip;
    /**
     * The whole media is shown, the clip over it (ranges on the timeline,
     * crop outlined) instead of being applied
     */
    whole?: boolean;
    /** Subtitle tracks of a video, none shown by default */
    subtitles?: SubtitleTrack[];
};

/**
 * Dispatches to the right player depending on the MIME type.
 */
export function FilePlayer({
    file,
    title,
    autoPlay,
    controls = true,
    fit = 'contain',
    className,
    onInteraction,
    tracking,
    clip,
    whole = false,
    subtitles,
}: FilePlayerProps) {
    const onContentClick = tracking ? () => contentClick(tracking) : undefined;
    const roi = clipForFile(clip, file.type);
    const fileClip = whole ? undefined : roi;
    const roiOverlay = whole ? roi : undefined;

    if (file.analysisPending || file.accepted === false) {
        return (
            <div
                className={cn(
                    'flex flex-col items-center justify-center gap-2',
                    className
                )}
            >
                <FileKindIcon mimeType={file.type} />
                <AnalysisChip file={file} />
            </div>
        );
    }
    if (!file.url) {
        return (
            <FileKindIcon
                mimeType={file.type}
                className={cn('size-16', className)}
            />
        );
    }
    switch (getFileKind(file.type)) {
        case FileKind.Image:
            if (fileClip?.crop) {
                return (
                    <CroppedImage
                        src={file.url}
                        alt={title ?? file.fileName}
                        crop={fileClip.crop}
                        className={className}
                        onClick={onContentClick}
                    />
                );
            }

            return fit === 'zoom' ? (
                <ZoomableImage
                    // The view (zoom, rotation) belongs to one image
                    key={file.id}
                    src={file.url}
                    alt={title ?? file.fileName}
                    className={className}
                    onInteraction={onInteraction}
                    onContentClick={onContentClick}
                    overlay={
                        roiOverlay?.crop ? (
                            <CropOverlay
                                crop={roiOverlay.crop}
                                dimmed={false}
                            />
                        ) : undefined
                    }
                />
            ) : (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                    src={file.url}
                    alt={title ?? file.fileName}
                    className={cn(
                        'max-h-full max-w-full object-contain',
                        className
                    )}
                    draggable={false}
                    onClick={onContentClick}
                />
            );
        case FileKind.Video:
            return controls ? (
                <VideoJsPlayer
                    // The playback and its frame belong to one video
                    key={file.id}
                    src={file.url}
                    type={file.type}
                    autoPlay={autoPlay}
                    className={className}
                    tracking={tracking}
                    timeRanges={fileClip?.timeRanges}
                    crop={fileClip?.crop}
                    roiOverlay={roiOverlay}
                    subtitles={subtitles}
                    annotate={fit === 'zoom'}
                />
            ) : (
                <VideoPreview
                    key={file.id}
                    src={file.url}
                    type={file.type}
                    autoPlay={autoPlay}
                    className={className}
                    tracking={tracking}
                    timeRanges={fileClip?.timeRanges}
                    crop={fileClip?.crop}
                />
            );
        case FileKind.Audio:
            return (
                <AudioPlayer
                    key={file.id}
                    src={file.url}
                    autoPlay={autoPlay}
                    controls={controls}
                    className={className}
                    timeRanges={roi?.timeRanges}
                    boundPlayback={!whole}
                />
            );
        case FileKind.Document:
            if (file.type === 'application/pdf') {
                return (
                    <PdfViewer
                        // The view (page, zoom) belongs to one document
                        key={file.id}
                        src={file.url}
                        controls={controls}
                        annotate={fit === 'zoom'}
                        className={className}
                        onInteraction={onInteraction}
                        onContentClick={onContentClick}
                    />
                );
            }

            return (
                <FileKindIcon
                    mimeType={file.type}
                    className={cn('size-16', className)}
                />
            );
        default:
            return (
                <FileKindIcon
                    mimeType={file.type}
                    className={cn('size-16', className)}
                />
            );
    }
}

/**
 * A video without controls (a preview): it loops, within its clip.
 */
function VideoPreview({
    src,
    type,
    autoPlay,
    className,
    tracking,
    timeRanges,
    crop,
}: {
    src: string;
    type: string;
    autoPlay?: boolean;
    className?: string;
    tracking?: ContentTracking;
    timeRanges?: ClipTimeRange[];
    crop?: ClipCrop;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    const trackingId = tracking?.id;
    const [aspect, setAspect] = useState<number>();
    useClipPlayback(ref, timeRanges, {loop: true});

    // MediaAnalytics reads the `data-matomo-*` attributes when it scans the
    // container, so they are rendered first
    useEffect(() => {
        const container = ref.current?.parentElement;
        if (trackingId && container) {
            scanForMedia(container);
        }
    }, [trackingId]);

    // Pause when scrolled out of view
    useEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (!e.isIntersecting && !el.paused) {
                    el.pause();
                }
            });
        });
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    // `src` is set on the element itself (not through `<source>`) so that a
    // change of file — prev/next navigation in the viewer — reloads the video.
    const video = (style?: CSSProperties, mediaClassName?: string) => (
        <video
            ref={ref}
            src={src}
            className={mediaClassName ?? cn('max-h-full max-w-full', className)}
            style={style}
            autoPlay={autoPlay}
            muted={autoPlay}
            // A clip loops through useClipPlayback
            loop={!timeRanges?.length}
            playsInline
            preload="metadata"
            data-type={type}
            data-matomo-resource={trackingId}
            data-matomo-title={trackingId ? tracking?.title : undefined}
            onLoadedMetadata={e => {
                const {videoWidth, videoHeight} = e.currentTarget;
                if (videoWidth > 0 && videoHeight > 0) {
                    setAspect(videoWidth / videoHeight);
                }
            }}
        />
    );

    if (!crop) {
        return video();
    }

    return (
        <ClipFrame crop={crop} aspect={aspect} className={className}>
            {media => video(media.style, media.className)}
        </ClipFrame>
    );
}

function CroppedImage({
    src,
    alt,
    crop,
    className,
    onClick,
}: {
    src: string;
    alt: string;
    crop: ClipCrop;
    className?: string;
    onClick?: () => void;
}) {
    const [aspect, setAspect] = useState<number>();

    return (
        <ClipFrame crop={crop} aspect={aspect} className={className}>
            {media => (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                    src={src}
                    alt={alt}
                    className={media.className}
                    style={media.style}
                    draggable={false}
                    onClick={onClick}
                    onLoad={e => {
                        const {naturalWidth, naturalHeight} = e.currentTarget;
                        if (naturalWidth > 0 && naturalHeight > 0) {
                            setAspect(naturalWidth / naturalHeight);
                        }
                    }}
                />
            )}
        </ClipFrame>
    );
}
