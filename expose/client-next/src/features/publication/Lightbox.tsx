'use client';

import {useEffect} from 'react';
import {createPortal} from 'react-dom';
import {useTranslation} from 'react-i18next';
import {ChevronLeftIcon, ChevronRightIcon, XIcon} from 'lucide-react';
import type {Asset, Publication} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {AssetPlayer} from '@/features/player/AssetPlayer';
import {AssetLegend} from './AssetLegend';
import {ThumbStrip} from './ThumbStrip';
import {cycle} from './assets';
import {useAssetViewLog} from './usePublication';
import {useAssetKeys} from './useAssetKeys';

/**
 * The asset over the whole window: its player, its legend on the side (below
 * on small screens), the strip of the thumbnails, the way to the previous /
 * next one (around the ends) and back to the publication. The page behind
 * does not scroll meanwhile.
 */
export function Lightbox({
    publication,
    assets,
    current,
    onMove,
    onClose,
}: {
    publication: Publication;
    assets: Asset[];
    current: Asset;
    onMove: (asset: Asset) => void;
    onClose: () => void;
}) {
    const {t} = useTranslation();
    const several = assets.length > 1;
    const previous = () => onMove(cycle(assets, current, -1));
    const next = () => onMove(cycle(assets, current, 1));
    useAssetViewLog(current.id);
    useAssetKeys({
        onPrevious: several ? previous : undefined,
        onNext: several ? next : undefined,
        onEscape: onClose,
    });

    useEffect(() => {
        const {style} = document.documentElement;
        const overflow = style.overflow;
        style.overflow = 'hidden';

        return () => {
            style.overflow = overflow;
        };
    }, []);

    return createPortal(
        <div
            role="dialog"
            aria-modal
            aria-label={current.title || current.originalName}
            data-testid="lightbox"
            className="fixed inset-0 z-40 flex flex-col bg-background/95 backdrop-blur-sm"
        >
            <div className="flex min-h-0 flex-1 flex-col md:flex-row">
                <div className="group/stage relative min-h-0 flex-1">
                    <AssetPlayer
                        asset={current}
                        className="size-full"
                        autoPlay
                    />
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
                        </>
                    ) : null}
                </div>
                <aside className="h-[40%] shrink-0 overflow-y-auto border-t p-4 pr-12 md:h-auto md:w-80 md:border-t-0 md:border-l lg:w-96">
                    <AssetLegend publication={publication} asset={current} />
                </aside>
            </div>
            {several ? (
                <ThumbStrip
                    assets={assets}
                    currentId={current.id}
                    onSelect={onMove}
                    className="border-t bg-background/80"
                />
            ) : null}
            <Button
                variant="secondary"
                size="icon"
                data-testid="lightbox-close"
                aria-label={t('common.close', 'Close')}
                title={t('common.close', 'Close')}
                onClick={onClose}
                className="absolute top-3 right-3 z-10 rounded-full shadow"
            >
                <XIcon />
            </Button>
        </div>,
        document.body
    );
}

export function NavButton({
    side,
    label,
    onClick,
}: {
    side: 'left' | 'right';
    label: string;
    onClick: () => void;
}) {
    return (
        <Button
            variant="secondary"
            size="icon"
            data-testid={side === 'left' ? 'asset-previous' : 'asset-next'}
            aria-label={label}
            title={label}
            onClick={onClick}
            className={
                (side === 'left' ? 'left-3' : 'right-3') +
                ' absolute top-1/2 z-10 -translate-y-1/2 rounded-full opacity-0 shadow transition-opacity group-hover/stage:opacity-90 focus-visible:opacity-100 pointer-coarse:opacity-80'
            }
        >
            {side === 'left' ? <ChevronLeftIcon /> : <ChevronRightIcon />}
        </Button>
    );
}
