'use client';

import {useCallback} from 'react';
import {useRouter} from 'next/navigation';
import type {Asset} from '@/types/api';
import {routes} from '@/lib/routes';
import {useNavigationContextStore} from './navigationContext';
import {useAssetViewerNavigation} from './view/AssetViewerNavigation';

export type OpenAssetOptions = {
    /** Ordered asset ids surrounding the opened asset (prev/next navigation) */
    siblings?: string[];
    renditionId?: string;
};

/**
 * Opens the asset viewer, remembering the surrounding list so the viewer can
 * offer previous / next navigation. From inside the viewer, the asset is shown
 * in place.
 */
export function useAssetOpener() {
    const router = useRouter();
    const setContext = useNavigationContextStore(s => s.set);
    const viewer = useAssetViewerNavigation();

    return useCallback(
        (asset: Asset, options: OpenAssetOptions = {}) => {
            if (options.siblings) {
                setContext({ids: options.siblings});
            }
            if (viewer) {
                viewer.goTo(asset.id, {renditionId: options.renditionId});

                return;
            }
            const url = routes.assetView(asset.id, options.renditionId);
            // A repeated click would stack the viewer once more
            if (url === window.location.pathname) {
                return;
            }
            router.push(url);
        },
        [router, setContext, viewer]
    );
}
