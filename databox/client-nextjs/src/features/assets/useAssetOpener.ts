'use client';

import {useCallback} from 'react';
import {useRouter} from 'next/navigation';
import type {Asset} from '@/types/api';
import {routes} from '@/lib/routes';
import {useNavigationContextStore} from './navigationContext';

export type OpenAssetOptions = {
    /** Ordered asset ids surrounding the opened asset (prev/next navigation) */
    siblings?: string[];
    renditionId?: string;
};

/**
 * Opens the asset viewer, remembering the surrounding list so the viewer can
 * offer previous / next navigation.
 */
export function useAssetOpener() {
    const router = useRouter();
    const setContext = useNavigationContextStore(s => s.set);

    return useCallback(
        (asset: Asset, options: OpenAssetOptions = {}) => {
            if (options.siblings) {
                setContext({ids: options.siblings});
            }
            router.push(routes.assetView(asset.id, options.renditionId));
        },
        [router, setContext]
    );
}
