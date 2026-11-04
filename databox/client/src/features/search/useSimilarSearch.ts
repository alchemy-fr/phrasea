'use client';

import {useCallback} from 'react';
import {useRouter} from 'next/navigation';
import {type Asset, EntityName} from '@/types/api';
import {routes} from '@/lib/routes';
import {iri} from '@/lib/utils/iri';
import {useEntitiesStore} from './entitiesStore';
import {searchStateToParams, similarSearchState} from './searchState';

/** Opens the search of the assets visually similar to the given one */
export function useSimilarSearch(): (asset: Asset) => void {
    const router = useRouter();
    const storeEntity = useEntitiesStore(s => s.store);

    return useCallback(
        (asset: Asset) => {
            // The condition shows the asset name without a round trip
            storeEntity(iri(EntityName.Asset, asset.id), asset as any);
            router.push(
                `${routes.assets()}?${searchStateToParams(similarSearchState(asset.id))}`
            );
        },
        [router, storeEntity]
    );
}
