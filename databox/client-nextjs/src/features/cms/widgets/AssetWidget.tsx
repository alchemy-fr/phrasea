'use client';

import {useQuery} from '@tanstack/react-query';
import {searchAssets} from '@/lib/api/assets';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {Skeleton} from '@/components/ui/misc';

export function AssetWidget({
    assetId,
    ids,
    layout = 'single',
}: {
    assetId?: string;
    ids?: string[];
    layout?: 'single' | 'carousel' | 'grid';
}) {
    const list = ids && ids.length > 0 ? ids : assetId ? [assetId] : [];
    const openAsset = useAssetOpener();
    const assets = useQuery({
        queryKey: ['cms-assets', list],
        queryFn: () => searchAssets({ids: list, limit: list.length || 1}),
        enabled: list.length > 0,
    });

    if (list.length === 0) {
        return null;
    }
    if (assets.isLoading) {
        return <Skeleton className="h-64 w-full" />;
    }
    const items = assets.data?.items ?? [];

    if (layout === 'single') {
        const asset = items[0];
        const file = asset?.preview?.file ?? asset?.thumbnail?.file;

        return asset && file ? (
            <figure className="flex flex-col items-center gap-2">
                <div className="max-h-[70vh] w-full overflow-hidden rounded-lg bg-media-bg">
                    <FilePlayer
                        file={file}
                        title={asset.name}
                        className="mx-auto max-h-[70vh]"
                    />
                </div>
                {asset.name ? (
                    <figcaption className="text-sm text-muted-foreground">
                        {asset.name}
                    </figcaption>
                ) : null}
            </figure>
        ) : null;
    }

    return (
        <div
            className={
                layout === 'carousel'
                    ? 'flex gap-3 overflow-x-auto pb-2'
                    : 'grid grid-cols-2 gap-3 md:grid-cols-4'
            }
        >
            {items.map(a => (
                <button
                    key={a.id}
                    type="button"
                    className={
                        layout === 'carousel'
                            ? 'h-64 w-80 shrink-0 overflow-hidden rounded-lg bg-media-bg'
                            : 'aspect-square overflow-hidden rounded-lg bg-media-bg'
                    }
                    onClick={() =>
                        openAsset(a, {siblings: items.map(i => i.id)})
                    }
                >
                    <AssetThumb asset={a} />
                </button>
            ))}
        </div>
    );
}
