'use client';

import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {
    ExternalLinkIcon,
    SearchIcon,
    Trash2Icon,
    ZoomInIcon,
} from 'lucide-react';
import type {Asset} from '@/types/api';
import {GeoCluster, searchAssets, SearchAssetsOptions} from '@/lib/api/assets';
import {Button} from '@/components/ui/button';
import {Spinner} from '@/components/ui/loader';
import {FileKindIcon} from '@/components/chips';
import {useAssetStore} from '@/features/assets/assetStore';
import {AssetPreview} from '@/features/assets/list/preview/AssetPreview';
import {formatNumber} from '@/lib/utils/format';
import {boundsToAQL, formatDistance, GeoCircle, padBounds} from './geo';

/** Thumbnails shown in the bubble of a cluster */
const CLUSTER_SAMPLE_SIZE = 9;

/** The search restricted to the area of a cluster, as an AQL condition */
export function clusterCondition(field: string, cluster: GeoCluster): string {
    return boundsToAQL(field, padBounds(cluster.bounds));
}

function Thumb({asset, onClick}: {asset: Asset; onClick: () => void}) {
    const thumb = asset.thumbnail?.file;

    return (
        <button
            type="button"
            title={asset.name}
            onClick={onClick}
            className="aspect-square cursor-pointer overflow-hidden rounded-md bg-muted ring-primary hover:ring-2 focus-visible:ring-2 focus-visible:outline-none"
        >
            {thumb?.url && thumb.type.startsWith('image/') ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                    src={thumb.url}
                    alt={asset.name ?? ''}
                    className="size-full object-cover"
                />
            ) : (
                <span className="flex size-full items-center justify-center">
                    <FileKindIcon
                        mimeType={asset.source?.type}
                        className="size-6"
                    />
                </span>
            )}
        </button>
    );
}

export function ClusterPopup({
    cluster,
    field,
    searchOptions,
    onZoom,
    onSearch,
    onOpen,
}: {
    cluster: GeoCluster;
    /** AQL field of the geo point attribute */
    field: string;
    searchOptions: SearchAssetsOptions;
    onZoom: () => void;
    onSearch: () => void;
    onOpen: (asset: Asset, siblings: string[]) => void;
}) {
    const {t, i18n} = useTranslation();
    const storeAssets = useAssetStore(s => s.setAssets);
    const sample = useQuery({
        queryKey: [
            'map-cluster',
            searchOptions,
            field,
            cluster.key,
            cluster.count,
        ],
        queryFn: async ({signal}) => {
            const result = await searchAssets(
                {
                    ...searchOptions,
                    conditions: [
                        ...(searchOptions.conditions ?? []),
                        clusterCondition(field, cluster),
                    ],
                    limit: CLUSTER_SAMPLE_SIZE,
                },
                signal
            );
            storeAssets(result.items);

            return result.items;
        },
    });
    const items = sample.data ?? [];
    const ids = items.map(a => a.id);

    return (
        <div data-testid="map-cluster-popup" className="w-64 space-y-3 text-sm">
            <div className="pr-6 font-medium">
                {t(
                    'map.cluster_assets',
                    '{{formattedCount}} assets in this area',
                    {
                        count: cluster.count,
                        formattedCount: formatNumber(
                            cluster.count,
                            i18n.language
                        ),
                    }
                )}
            </div>
            {sample.isPending ? (
                <div className="flex h-20 items-center justify-center">
                    <Spinner />
                </div>
            ) : sample.isError ? (
                <div className="text-xs text-destructive">
                    {t('map.cluster_error', 'Could not load the assets')}
                </div>
            ) : (
                <div className="grid grid-cols-3 gap-1.5">
                    {items.map(asset => (
                        <Thumb
                            key={asset.id}
                            asset={asset}
                            onClick={() => onOpen(asset, ids)}
                        />
                    ))}
                </div>
            )}
            {cluster.count > items.length && items.length > 0 ? (
                <div className="text-xs text-muted-foreground">
                    {t('map.cluster_more', 'and {{count}} more', {
                        count: cluster.count - items.length,
                    })}
                </div>
            ) : null}
            <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={onZoom}>
                    <ZoomInIcon /> {t('map.zoom_in', 'Zoom in')}
                </Button>
                <Button
                    size="sm"
                    data-testid="map-cluster-search"
                    onClick={onSearch}
                >
                    <SearchIcon /> {t('map.search_area', 'Search this area')}
                </Button>
            </div>
        </div>
    );
}

export function AssetPopup({
    assetId,
    onOpen,
}: {
    assetId: string;
    onOpen: (asset: Asset) => void;
}) {
    const {t} = useTranslation();
    const asset = useAssetStore(s => s.assets[assetId]);
    const storeAssets = useAssetStore(s => s.setAssets);
    // Loaded with the thumbnail markers, unless they are too many to show
    const load = useQuery({
        queryKey: ['map-asset', assetId],
        queryFn: async ({signal}) => {
            const result = await searchAssets(
                {ids: [assetId], limit: 1},
                signal
            );
            storeAssets(result.items);

            return result.items.length;
        },
        enabled: !asset,
    });
    if (!asset && (load.isError || load.data === 0)) {
        return (
            <div className="pr-6 text-xs text-destructive">
                {t('map.cluster_error', 'Could not load the assets')}
            </div>
        );
    }
    if (!asset) {
        return (
            <div className="flex h-20 w-48 items-center justify-center">
                <Spinner />
            </div>
        );
    }
    return (
        <div
            data-testid="map-asset-popup"
            className="w-[min(36rem,calc(100vw-5rem))] space-y-2 text-sm"
        >
            {/* Shown as the hover preview of the grid */}
            <div className="flex h-72 overflow-hidden rounded-md border bg-popover">
                <AssetPreview asset={asset} interactive />
            </div>
            <Button size="sm" className="w-full" onClick={() => onOpen(asset)}>
                <ExternalLinkIcon /> {t('map.open_asset', 'Open')}
            </Button>
        </div>
    );
}

export function ZonePopup({
    circle,
    onZoom,
    onRemove,
}: {
    circle: GeoCircle;
    onZoom: () => void;
    onRemove: () => void;
}) {
    const {t, i18n} = useTranslation();

    return (
        <div data-testid="map-zone-popup" className="w-52 space-y-3 text-sm">
            <div className="pr-6">
                <div className="font-medium">
                    {t('map.zone', 'Search zone')}
                </div>
                <div className="text-xs text-muted-foreground">
                    {t('map.zone_radius', 'Radius: {{radius}}', {
                        radius: formatDistance(circle.radius, i18n.language),
                    })}
                </div>
            </div>
            <p className="text-xs text-muted-foreground">
                {t(
                    'map.zone_edit_help',
                    'Drag the zone to move it, or its edge to resize it.'
                )}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={onZoom}>
                    <ZoomInIcon /> {t('map.zoom_in', 'Zoom in')}
                </Button>
                <Button size="sm" variant="destructive" onClick={onRemove}>
                    <Trash2Icon /> {t('map.remove_zone', 'Remove')}
                </Button>
            </div>
        </div>
    );
}
