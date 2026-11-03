'use client';

import {type CSSProperties, useEffect, useRef, useState} from 'react';
import dynamic from 'next/dynamic';
import type {ApiFile, BasketClip, ClipCrop, ClipTimeRange} from '@/types/api';
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
import {clipForFile} from '@/features/baskets/clip/clip';
import {ClipFrame} from '@/features/baskets/clip/ClipFrame';
import {useClipPlayback} from '@/features/baskets/clip/useClipPlayback';
import {Spinner} from '@/components/ui/loader';

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
     * Clip of the basket item displayed: bounds the playback and crops the
     * media, for the parts that apply to the file
     */
    clip?: BasketClip;
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
}: FilePlayerProps) {
    const onContentClick = tracking ? () => contentClick(tracking) : undefined;
    const fileClip = clipForFile(clip, file.type);

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
            return (
                <VideoPlayer
                    key={file.id}
                    src={file.url}
                    type={file.type}
                    autoPlay={autoPlay}
                    controls={controls}
                    className={className}
                    tracking={tracking}
                    timeRange={fileClip?.timeRange}
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
                    timeRange={fileClip?.timeRange}
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

function VideoPlayer({
    src,
    type,
    autoPlay,
    controls,
    className,
    tracking,
    timeRange,
    crop,
}: {
    src: string;
    type: string;
    autoPlay?: boolean;
    controls: boolean;
    className?: string;
    tracking?: ContentTracking;
    timeRange?: ClipTimeRange;
    crop?: ClipCrop;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    const trackingId = tracking?.id;
    const [aspect, setAspect] = useState<number>();
    // Without controls, the video loops: within the clip
    useClipPlayback(ref, timeRange, {loop: !controls});

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
            // The native controls would be cropped with the video
            controls={controls && !crop}
            autoPlay={autoPlay}
            muted={autoPlay}
            // A clip loops through useClipPlayback
            loop={!controls && !timeRange}
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

    // Without the native controls, a click plays or pauses
    const togglePlay = () => {
        const el = ref.current;
        if (!el) {
            return;
        }
        if (el.paused) {
            el.play()?.catch(() => undefined);
        } else {
            el.pause();
        }
    };

    return (
        <ClipFrame
            crop={crop}
            aspect={aspect}
            className={cn(controls && 'cursor-pointer', className)}
        >
            {media => (
                <div
                    className="contents"
                    onClick={controls ? togglePlay : undefined}
                >
                    {video(media.style, media.className)}
                </div>
            )}
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
