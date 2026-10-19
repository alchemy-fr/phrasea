'use client';

import {useEffect, useRef} from 'react';
import type {ApiFile} from '@/types/api';
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

export type FilePlayerProps = {
    file: ApiFile;
    title?: string;
    autoPlay?: boolean;
    controls?: boolean;
    /** `contain` keeps the whole media visible; `zoom` enables zoom/pan (viewer) */
    fit?: 'contain' | 'zoom';
    className?: string;
    onInteraction?: () => void;
    /**
     * The asset displayed, for Matomo: clicks on an image or a PDF, video
     * playback (MediaAnalytics)
     */
    tracking?: ContentTracking;
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
}: FilePlayerProps) {
    const onContentClick = tracking ? () => contentClick(tracking) : undefined;

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
                />
            );
        case FileKind.Document:
            if (file.type === 'application/pdf') {
                return (
                    <PdfPlayer
                        src={file.url}
                        className={className}
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
}: {
    src: string;
    type: string;
    autoPlay?: boolean;
    controls: boolean;
    className?: string;
    tracking?: ContentTracking;
}) {
    const ref = useRef<HTMLVideoElement>(null);
    const trackingId = tracking?.id;

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
    return (
        <video
            ref={ref}
            src={src}
            className={cn('max-h-full max-w-full', className)}
            controls={controls}
            autoPlay={autoPlay}
            muted={autoPlay}
            loop={!controls}
            playsInline
            preload="metadata"
            data-type={type}
            data-matomo-resource={trackingId}
            data-matomo-title={trackingId ? tracking?.title : undefined}
        />
    );
}

/**
 * The clicks inside the PDF viewer (an iframe) never reach the page: the
 * first one is noticed by the focus moving into the iframe.
 */
function PdfPlayer({
    src,
    className,
    onContentClick,
}: {
    src: string;
    className?: string;
    onContentClick?: () => void;
}) {
    const ref = useRef<HTMLIFrameElement>(null);
    const onClickRef = useRef(onContentClick);
    onClickRef.current = onContentClick;

    useEffect(() => {
        let timeout: ReturnType<typeof setTimeout> | undefined;
        const onBlur = () => {
            // The iframe becomes the active element once the blur is over
            timeout = setTimeout(() => {
                if (document.activeElement === ref.current) {
                    onClickRef.current?.();
                }
            }, 0);
        };
        window.addEventListener('blur', onBlur);

        return () => {
            window.removeEventListener('blur', onBlur);
            clearTimeout(timeout);
        };
    }, []);

    return (
        <iframe
            ref={ref}
            src={`${src}#toolbar=1&view=FitH`}
            title="PDF"
            className={cn('size-full border-0 bg-white', className)}
        />
    );
}
