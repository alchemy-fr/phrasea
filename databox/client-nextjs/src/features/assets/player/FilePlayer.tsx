'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {MaximizeIcon, MinusIcon, PlusIcon, RotateCcwIcon} from 'lucide-react';
import type {ApiFile} from '@/types/api';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {FileKindIcon} from '@/components/chips';
import {AnalysisChip} from '@/features/assets/quarantine/AnalysisChip';
import {Button} from '@/components/ui/button';
import {cn} from '@/lib/utils/cn';
import {clamp} from '@/lib/utils/misc';

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
                    src={file.url}
                    type={file.type}
                    autoPlay={autoPlay}
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

    return (
        <video
            ref={ref}
            className={cn('max-h-full max-w-full', className)}
            controls={controls}
            autoPlay={autoPlay}
            muted={autoPlay}
            loop={!controls}
            playsInline
            preload="metadata"
        >
            <source src={src} type={type} />
        </video>
    );
}

function AudioPlayer({
    src,
    type,
    autoPlay,
    className,
}: {
    src: string;
    type: string;
    autoPlay?: boolean;
    className?: string;
}) {
    const {t} = useTranslation();

    return (
        <div
            className={cn(
                'flex w-full max-w-xl flex-col items-center gap-3 p-4',
                className
            )}
        >
            <FileKindIcon mimeType={type} className="size-16" />
            <audio
                controls
                autoPlay={autoPlay}
                className="w-full"
                preload="metadata"
                aria-label={t('player.audio', 'Audio player')}
            >
                <source src={src} type={type} />
            </audio>
        </div>
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

/**
 * Image with mouse-wheel zoom and drag panning. Double-click resets.
 */
export function ZoomableImage({
    src,
    alt,
    className,
    onInteraction,
}: {
    src: string;
    alt: string;
    className?: string;
    onInteraction?: () => void;
}) {
    const {t} = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(1);
    const [offset, setOffset] = useState({x: 0, y: 0});
    const drag = useRef<{x: number; y: number; ox: number; oy: number} | null>(
        null
    );

    const reset = () => {
        setScale(1);
        setOffset({x: 0, y: 0});
    };
    const zoomBy = (factor: number, origin?: {x: number; y: number}) => {
        setScale(prev => {
            const next = clamp(prev * factor, 0.1, 20);
            if (origin) {
                const ratio = next / prev;
                setOffset(o => ({
                    x: origin.x - (origin.x - o.x) * ratio,
                    y: origin.y - (origin.y - o.y) * ratio,
                }));
            }

            return next;
        });
    };

    return (
        <div
            ref={containerRef}
            className={cn(
                'relative size-full overflow-hidden select-none',
                scale > 1
                    ? 'cursor-grab active:cursor-grabbing'
                    : 'cursor-zoom-in',
                className
            )}
            onWheel={e => {
                e.preventDefault();
                const rect = containerRef.current!.getBoundingClientRect();
                const origin = {
                    x: e.clientX - rect.left - rect.width / 2,
                    y: e.clientY - rect.top - rect.height / 2,
                };
                zoomBy(e.deltaY < 0 ? 1.15 : 1 / 1.15, origin);
                onInteraction?.();
            }}
            onMouseDown={e => {
                if (e.button !== 0) {
                    return;
                }
                drag.current = {
                    x: e.clientX,
                    y: e.clientY,
                    ox: offset.x,
                    oy: offset.y,
                };
            }}
            onMouseMove={e => {
                if (!drag.current) {
                    return;
                }
                setOffset({
                    x: drag.current.ox + e.clientX - drag.current.x,
                    y: drag.current.oy + e.clientY - drag.current.y,
                });
            }}
            onMouseUp={() => (drag.current = null)}
            onMouseLeave={() => (drag.current = null)}
            onDoubleClick={() => {
                if (scale === 1) {
                    zoomBy(2);
                } else {
                    reset();
                }
                onInteraction?.();
            }}
        >
            <div className="flex size-full items-center justify-center">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                    src={src}
                    alt={alt}
                    draggable={false}
                    className="max-h-full max-w-full object-contain transition-transform duration-75"
                    style={{
                        transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})`,
                    }}
                    onClick={onInteraction}
                />
            </div>
            <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full border bg-background/90 p-1 shadow-md">
                <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={() => zoomBy(1 / 1.25)}
                    aria-label={t('player.zoom_out', 'Zoom out')}
                >
                    <MinusIcon />
                </Button>
                <span className="w-12 text-center font-mono text-xs">
                    {Math.round(scale * 100)}%
                </span>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={() => zoomBy(1.25)}
                    aria-label={t('player.zoom_in', 'Zoom in')}
                >
                    <PlusIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={reset}
                    aria-label={t('player.fit', 'Fit to screen')}
                >
                    <MaximizeIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={reset}
                    aria-label={t('common.reset', 'Reset')}
                >
                    <RotateCcwIcon />
                </Button>
            </div>
        </div>
    );
}
