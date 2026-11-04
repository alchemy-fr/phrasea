'use client';

import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import type {Asset} from '@/types/api';
import {cn} from '@alchemy/next-ui/utils/cn';
import {Thumbnail} from './Thumbnail';

/**
 * Horizontal strip of the thumbnails of the assets, the current one
 * highlighted and kept in the middle; the wheel scrolls it horizontally.
 */
export function ThumbStrip({
    assets,
    currentId,
    onSelect,
    className,
}: {
    assets: Asset[];
    currentId: string | undefined;
    onSelect: (asset: Asset) => void;
    className?: string;
}) {
    const {t} = useTranslation();
    const ref = useRef<HTMLDivElement>(null);

    // The strip alone scrolls: `scrollIntoView` would scroll the page too
    useEffect(() => {
        const strip = ref.current;
        const current = strip?.querySelector<HTMLElement>(
            '[aria-current="true"]'
        );
        if (!strip || !current) {
            return;
        }
        strip.scrollTo({
            left:
                current.offsetLeft -
                (strip.clientWidth - current.offsetWidth) / 2,
            behavior: 'smooth',
        });
    }, [currentId]);

    // Not through React: its wheel listeners are passive
    useEffect(() => {
        const el = ref.current!;
        const onWheel = (e: WheelEvent) => {
            if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                e.preventDefault();
                el.scrollLeft += e.deltaY;
            }
        };
        el.addEventListener('wheel', onWheel, {passive: false});

        return () => el.removeEventListener('wheel', onWheel);
    }, []);

    return (
        <div
            ref={ref}
            data-testid="thumb-strip"
            role="listbox"
            aria-label={t('asset.thumbnails', 'Assets')}
            className={cn(
                'scrollbar-thin relative flex h-20 shrink-0 gap-1.5 overflow-x-auto overflow-y-hidden p-1',
                className
            )}
        >
            {assets.map(asset => {
                const current = asset.id === currentId;

                return (
                    <button
                        key={asset.id}
                        type="button"
                        role="option"
                        aria-selected={current}
                        aria-current={current}
                        title={asset.title || asset.originalName}
                        onClick={() => onSelect(asset)}
                        className={cn(
                            'h-full w-18 shrink-0 cursor-pointer overflow-hidden rounded-md opacity-60 ring-offset-1 ring-offset-background transition-opacity outline-none hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring',
                            current && 'opacity-100 ring-2 ring-primary'
                        )}
                    >
                        <Thumbnail
                            asset={asset}
                            className="size-full [&_svg]:size-6"
                        />
                    </button>
                );
            })}
        </div>
    );
}
