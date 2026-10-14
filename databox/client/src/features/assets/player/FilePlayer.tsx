'use client';

import {useEffect, useRef} from 'react';
import type {ApiFile} from '@/types/api';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {FileKindIcon} from '@/components/chips';
import {AnalysisChip} from '@/features/assets/quarantine/AnalysisChip';
import {cn} from '@/lib/utils/cn';
import {AudioPlayer} from '@/features/assets/player/AudioPlayer';
import {ZoomableImage} from '@/features/assets/player/ZoomableImage';

export type FilePlayerProps = {
    file: ApiFile;
    title?: string;
    autoPlay?: boolean;
    controls?: boolean;
    /** `contain` keeps the whole media visible; `zoom` enables zoom/pan (viewer) */
    fit?: 'contain' | 'zoom';
    className?: string;
    onInteraction?: () => void;
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
}: FilePlayerProps) {
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
                return <PdfPlayer src={file.url} className={className} />;
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
}: {
    src: string;
    type: string;
    autoPlay?: boolean;
    controls: boolean;
    className?: string;
}) {
    const ref = useRef<HTMLVideoElement>(null);

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
        />
    );
}

function PdfPlayer({src, className}: {src: string; className?: string}) {
    return (
        <iframe
            src={`${src}#toolbar=1&view=FitH`}
            title="PDF"
            className={cn('size-full border-0 bg-white', className)}
        />
    );
}
