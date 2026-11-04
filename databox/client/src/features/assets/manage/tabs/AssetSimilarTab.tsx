'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {ScanSearchIcon} from 'lucide-react';
import type {AssetTabProps} from '../types';
import {getSimilarAssets} from '@/lib/api/assets';
import {Button} from '@alchemy/next-ui/ui/button';
import {Skeleton} from '@alchemy/next-ui/ui/misc';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {useSimilarSearch} from '@/features/search/useSimilarSearch';

const limit = 24;

/**
 * The assets visually closest to the displayed one, and the way to the
 * whole similarity search.
 */
export function AssetSimilarTab({asset}: AssetTabProps) {
    const {t} = useTranslation();
    const openAsset = useAssetOpener();
    const searchSimilar = useSimilarSearch();
    const similar = useQuery({
        queryKey: ['asset-similar', asset.id],
        queryFn: ({signal}) => getSimilarAssets(asset.id, limit, signal),
    });
    const items = similar.data?.items ?? [];

    return (
        <div className="space-y-3" data-testid="asset-similar">
            <Button
                variant="outline"
                size="sm"
                onClick={() => searchSimilar(asset)}
            >
                <ScanSearchIcon />{' '}
                {t('asset.similar.search', 'Search similar assets')}
            </Button>
            {similar.isLoading ? (
                <Skeleton className="h-24" />
            ) : items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {similar.isError
                        ? t(
                              'asset.similar.error',
                              'Similar assets could not be loaded'
                          )
                        : t(
                              'asset.similar.empty',
                              'No similar asset found. The similarity of this asset may not have been computed yet.'
                          )}
                </p>
            ) : (
                <ul className="grid grid-cols-3 gap-2">
                    {items.map(a => (
                        <li key={a.id}>
                            <Tooltip content={a.name}>
                                <button
                                    type="button"
                                    className="block aspect-square w-full overflow-hidden rounded bg-media-bg hover:ring-2 hover:ring-primary"
                                    onClick={() => openAsset(a)}
                                    aria-label={a.name}
                                >
                                    <AssetThumb
                                        asset={a}
                                        size={120}
                                        score={similar.data?.scores[a.id]}
                                        ignoreAnalysis
                                    />
                                </button>
                            </Tooltip>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
