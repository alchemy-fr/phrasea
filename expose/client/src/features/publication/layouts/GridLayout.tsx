'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {Asset, Publication} from '@/types/api';
import {Thumbnail} from '../Thumbnail';
import {useAssetRoute} from '../useAssetRoute';
import {Lightbox} from '../Lightbox';

/** Height of a row of the mosaic, in px */
export const ROW_HEIGHT = 180;
/** Assets added to the mosaic at a time, as the user scrolls down */
const BATCH = 60;
/** Aspect ratio of a thumbnail not loaded yet, or without image */
const DEFAULT_RATIO = 1;

/**
 * Justified mosaic: rows of a fixed height filling the width, the images
 * growing to fill their row and cropped to fit it; the last row is not
 * stretched. Each image gets its width from its own ratio once loaded — the
 * mosaic shows up at once and settles as the thumbnails arrive — and the
 * assets are added by batches as the user scrolls.
 *
 * A click opens the asset in the viewer, at its own URL.
 */
export function GridLayout({
    publication,
    assets,
    current,
}: {
    publication: Publication;
    assets: Asset[];
    current: Asset | undefined;
}) {
    const {t} = useTranslation();
    const route = useAssetRoute(publication);
    const [ratios, setRatios] = useState<Record<string, number>>({});
    const [count, setCount] = useState(BATCH);
    const sentinel = useRef<HTMLDivElement>(null);
    const shown = assets.slice(0, count);
    const more = count < assets.length;

    useEffect(() => {
        const el = sentinel.current;
        if (!el || !more) {
            return;
        }
        const observer = new IntersectionObserver(
            entries => {
                if (entries.some(e => e.isIntersecting)) {
                    setCount(c => c + BATCH);
                }
            },
            {rootMargin: '600px'}
        );
        observer.observe(el);

        return () => observer.disconnect();
    }, [more, count]);

    return (
        <>
            <ul
                data-testid="grid-layout"
                aria-label={t('asset.list', 'Assets')}
                className="flex flex-wrap gap-0.5"
            >
                {shown.map(asset => {
                    const ratio = ratios[asset.id] ?? DEFAULT_RATIO;

                    return (
                        <li
                            key={asset.id}
                            className="relative overflow-hidden"
                            style={{
                                height: ROW_HEIGHT,
                                flexGrow: ratio,
                                flexBasis: ratio * ROW_HEIGHT,
                            }}
                        >
                            <button
                                type="button"
                                data-testid="grid-item"
                                title={asset.title || asset.originalName}
                                onClick={() => route.open(asset)}
                                className="group size-full cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                            >
                                <Thumbnail
                                    asset={asset}
                                    className="size-full"
                                    imgClassName="transition-[transform,opacity] duration-300 group-hover:scale-130 group-hover:opacity-85 motion-reduce:transition-none"
                                    onSize={(w, h) =>
                                        setRatios(r =>
                                            r[asset.id] === w / h
                                                ? r
                                                : {...r, [asset.id]: w / h}
                                        )
                                    }
                                />
                            </button>
                        </li>
                    );
                })}
                {/* Takes the free space of the last row, which keeps the
                    size of the others */}
                <li aria-hidden className="h-0 grow-[1000]" />
            </ul>
            {more ? <div ref={sentinel} className="h-px" /> : null}
            {current ? (
                <Lightbox
                    publication={publication}
                    assets={assets}
                    current={current}
                    onMove={route.move}
                    onClose={route.close}
                />
            ) : null}
        </>
    );
}
