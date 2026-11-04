'use client';

import {useTranslation} from 'react-i18next';
import type {Asset, Publication} from '@/types/api';
import {AssetPlayer} from '@/features/player/AssetPlayer';
import {AssetLegend} from '../AssetLegend';
import {ThumbStrip} from '../ThumbStrip';
import {NavButton} from '../Lightbox';
import {cycle} from '../assets';
import {useAssetRoute} from '../useAssetRoute';
import {useAssetViewLog} from '../usePublication';
import {useAssetKeys} from '../useAssetKeys';

/**
 * One asset at a time, in a large player: the one of the URL, else the
 * first. With several, the previous / next ones (around the ends), the
 * position and the strip of the thumbnails; Escape goes back to the first.
 */
export function GalleryLayout({
    publication,
    assets,
    current: selected,
}: {
    publication: Publication;
    assets: Asset[];
    current: Asset | undefined;
}) {
    const {t} = useTranslation();
    const route = useAssetRoute(publication);
    const current = selected ?? assets[0];
    const index = assets.findIndex(a => a.id === current.id);
    const several = assets.length > 1;
    const previous = () => route.move(cycle(assets, current, -1));
    const next = () => route.move(cycle(assets, current, 1));
    useAssetViewLog(current.id);
    useAssetKeys({
        onPrevious: several ? previous : undefined,
        onNext: several ? next : undefined,
        onEscape: selected ? route.close : undefined,
    });

    return (
        <div data-testid="gallery-layout" className="flex flex-col gap-4">
            <div className="group/stage relative h-[450px] overflow-hidden rounded-lg bg-muted/40 md:h-[500px] lg:h-[600px] xl:h-[700px]">
                <AssetPlayer asset={current} className="size-full" />
                {several ? (
                    <>
                        <NavButton
                            side="left"
                            label={t('asset.previous', 'Previous')}
                            onClick={previous}
                        />
                        <NavButton
                            side="right"
                            label={t('asset.next', 'Next')}
                            onClick={next}
                        />
                        <span
                            data-testid="gallery-position"
                            className="pointer-events-none absolute top-3 right-3 rounded-full bg-background/80 px-2.5 py-1 font-mono text-xs tabular-nums shadow-sm"
                        >
                            {index + 1} / {assets.length}
                        </span>
                    </>
                ) : null}
            </div>
            {several ? (
                <ThumbStrip
                    assets={assets}
                    currentId={current.id}
                    onSelect={route.move}
                />
            ) : null}
            <AssetLegend publication={publication} asset={current} />
        </div>
    );
}
