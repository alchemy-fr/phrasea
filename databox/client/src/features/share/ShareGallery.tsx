'use client';

import {useTranslation} from 'react-i18next';
import {
    DownloadIcon,
    LayoutDashboardIcon,
    LayoutGridIcon,
    ListIcon,
} from 'lucide-react';
import type {Asset} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Slider} from '@/components/ui/controls';
import {Tabs, TabsList, TabsTrigger} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {Masonry} from '@/components/ui/masonry';
import {FileTypeChip} from '@/components/chips';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import type {LayoutMode} from '@/features/preferences/store';
import {formatFileSize} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {useShareDisplayStore} from './shareDisplayStore';

type ItemProps = {
    asset: Asset;
    onOpen: (asset: Asset) => void;
    onDownload: (asset: Asset) => void;
};

/**
 * The assets of a share, as a grid, a masonry or a list (the visitor's
 * choice, remembered on this browser). Clicking an asset opens it.
 */
export function ShareGallery({
    assets,
    onOpen,
    onDownload,
}: {
    assets: Asset[];
    onOpen: (asset: Asset) => void;
    onDownload: (asset: Asset) => void;
}) {
    const layout = useShareDisplayStore(s => s.layout);
    const thumbSize = useShareDisplayStore(s => s.thumbSize);

    if (layout === 'list') {
        return (
            <ul
                data-testid="share-gallery"
                data-layout={layout}
                className="divide-y rounded-lg border bg-card"
            >
                {assets.map(asset => (
                    <ListItem
                        key={asset.id}
                        asset={asset}
                        onOpen={onOpen}
                        onDownload={onDownload}
                    />
                ))}
            </ul>
        );
    }

    if (layout === 'masonry') {
        return (
            <div data-testid="share-gallery" data-layout={layout}>
                <Masonry
                    items={assets}
                    columnWidth={thumbSize}
                    gap={16}
                    getKey={a => a.id}
                    renderItem={asset => (
                        <Card
                            asset={asset}
                            onOpen={onOpen}
                            onDownload={onDownload}
                            natural
                        />
                    )}
                />
            </div>
        );
    }

    return (
        <div
            data-testid="share-gallery"
            data-layout={layout}
            className="grid gap-4"
            style={{
                gridTemplateColumns: `repeat(auto-fill, minmax(${thumbSize}px, 1fr))`,
            }}
        >
            {assets.map(asset => (
                <Card
                    key={asset.id}
                    asset={asset}
                    onOpen={onOpen}
                    onDownload={onDownload}
                    height={thumbSize}
                />
            ))}
        </div>
    );
}

/** Layout switch and thumbnail size of the gallery */
export function ShareGalleryControls() {
    const {t} = useTranslation();
    const layout = useShareDisplayStore(s => s.layout);
    const thumbSize = useShareDisplayStore(s => s.thumbSize);
    const set = useShareDisplayStore(s => s.set);

    return (
        <div className="flex items-center gap-3">
            {layout !== 'list' ? (
                <Slider
                    className="hidden w-28 sm:flex"
                    aria-label={t('display.thumb_size', 'Thumbnail size')}
                    min={120}
                    max={480}
                    step={20}
                    value={[thumbSize]}
                    onValueChange={([v]) => set({thumbSize: v})}
                />
            ) : null}
            <Tabs
                value={layout}
                onValueChange={v => set({layout: v as LayoutMode})}
            >
                <TabsList
                    data-testid="share-layout"
                    aria-label={t('display.layout', 'Layout')}
                >
                    <LayoutTab value="grid" label={t('display.grid', 'Grid')}>
                        <LayoutGridIcon />
                    </LayoutTab>
                    <LayoutTab
                        value="masonry"
                        label={t('display.masonry', 'Masonry')}
                    >
                        <LayoutDashboardIcon />
                    </LayoutTab>
                    <LayoutTab value="list" label={t('display.list', 'List')}>
                        <ListIcon />
                    </LayoutTab>
                </TabsList>
            </Tabs>
        </div>
    );
}

function LayoutTab({
    value,
    label,
    children,
}: {
    value: LayoutMode;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <Tooltip content={label}>
            <TabsTrigger value={value} aria-label={label} className="px-2">
                {children}
            </TabsTrigger>
        </Tooltip>
    );
}

function Card({
    asset,
    onOpen,
    onDownload,
    height,
    natural,
}: ItemProps & {height?: number; natural?: boolean}) {
    const {t} = useTranslation();

    return (
        <div
            data-testid="share-asset"
            className="group/item relative flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground shadow-xs transition-shadow hover:shadow-md"
        >
            <button
                type="button"
                className="relative block w-full cursor-zoom-in overflow-hidden bg-media-bg text-left focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                style={height ? {height} : undefined}
                aria-label={asset.name ?? undefined}
                onClick={() => onOpen(asset)}
            >
                <AssetThumb asset={asset} size={height} natural={natural} />
            </button>
            <div className="flex items-center gap-1 py-1 pr-1 pl-3">
                <button
                    type="button"
                    data-testid="share-asset-title"
                    className="min-w-0 flex-1 truncate py-1 text-left text-sm font-medium hover:underline"
                    title={asset.name ?? undefined}
                    onClick={() => onOpen(asset)}
                >
                    {asset.name || '—'}
                </button>
                <Tooltip content={t('share.download.title', 'Download')}>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        data-testid="share-asset-download"
                        aria-label={t('share.download.title', 'Download')}
                        className="text-muted-foreground"
                        onClick={() => onDownload(asset)}
                    >
                        <DownloadIcon />
                    </Button>
                </Tooltip>
            </div>
        </div>
    );
}

function ListItem({asset, onOpen, onDownload}: ItemProps) {
    const {t, i18n} = useTranslation();
    const source = asset.source;

    return (
        <li
            data-testid="share-asset"
            className="flex items-center gap-3 px-2 py-2 transition-colors hover:bg-accent/50"
        >
            <button
                type="button"
                className="flex min-w-0 flex-1 items-center gap-3 text-left focus-visible:outline-none"
                onClick={() => onOpen(asset)}
            >
                <span className="size-14 shrink-0 overflow-hidden rounded-md bg-media-bg">
                    <AssetThumb asset={asset} size={56} />
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span
                        data-testid="share-asset-title"
                        className="truncate text-sm font-medium"
                    >
                        {asset.name || '—'}
                    </span>
                    {source ? (
                        <span className="flex items-center gap-2 text-xs text-muted-foreground">
                            <span
                                className={cn('flex', !source.type && 'hidden')}
                            >
                                <FileTypeChip
                                    mimeType={source.type}
                                    extension={source.extension || undefined}
                                />
                            </span>
                            {source.size ? (
                                <span className="tabular-nums">
                                    {formatFileSize(
                                        source.size,
                                        true,
                                        i18n.language
                                    )}
                                </span>
                            ) : null}
                        </span>
                    ) : null}
                </span>
            </button>
            <Button
                variant="outline"
                size="sm"
                data-testid="share-asset-download"
                onClick={() => onDownload(asset)}
            >
                <DownloadIcon />
                <span className="hidden sm:inline">
                    {t('share.download.title', 'Download')}
                </span>
            </Button>
        </li>
    );
}
