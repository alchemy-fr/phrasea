'use client';

import {useCallback, useRef, useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {getStoryThumbnails} from '@/lib/api/assets';
import {Skeleton} from '@/components/ui/misc';
import {cn} from '@/lib/utils/cn';

/**
 * Hover carousel of a story: the thumbnails of its items laid out in a
 * horizontal strip, scrolled with the horizontal position of the pointer
 * (like the old client). Thumbnails are only fetched once hovered.
 */
export function StoryThumb({
    assetId,
    size,
    active,
    className,
}: {
    assetId: string;
    size: number;
    /** The strip is displayed (and loaded) while hovered */
    active: boolean;
    className?: string;
}) {
    const [requested, setRequested] = useState(false);
    const stripRef = useRef<HTMLDivElement>(null);
    if (active && !requested) {
        setRequested(true);
    }

    const query = useQuery({
        queryKey: ['story-thumbnails', assetId],
        queryFn: () => getStoryThumbnails(assetId),
        enabled: requested,
        staleTime: 60_000,
    });

    const onMouseMove = useCallback((e: React.MouseEvent<HTMLDivElement>) => {
        const strip = stripRef.current;
        if (!strip) {
            return;
        }
        const sideOffset = 10;
        const box = e.currentTarget.getBoundingClientRect();
        const ratio = Math.min(
            1,
            Math.max(0, e.clientX - sideOffset - box.left) /
                Math.max(1, box.width - 2 * sideOffset)
        );
        strip.scrollLeft = ratio * (strip.scrollWidth - box.width);
    }, []);

    const thumbnails = query.data;

    return (
        <div
            ref={stripRef}
            data-testid="story-thumb"
            onMouseMove={onMouseMove}
            className={cn(
                'absolute inset-0 flex items-center overflow-hidden transition-opacity duration-500',
                active ? 'opacity-100' : 'pointer-events-none opacity-0',
                thumbnails?.length ? 'bg-black' : 'bg-background',
                className
            )}
        >
            {thumbnails ? (
                <div className="flex h-full shrink-0 flex-row items-center">
                    {thumbnails.map((url, index) => (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                            key={index}
                            src={url}
                            alt=""
                            draggable={false}
                            loading="lazy"
                            className="block h-full w-auto shrink-0 object-contain"
                            style={{maxWidth: size * 1.5}}
                        />
                    ))}
                </div>
            ) : (
                <Skeleton className="size-full rounded-none" />
            )}
        </div>
    );
}
