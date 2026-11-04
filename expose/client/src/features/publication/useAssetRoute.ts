'use client';

import {useCallback} from 'react';
import {useRouter} from 'next/navigation';
import type {Asset, Publication} from '@/types/api';
import {routes} from '@/lib/routes';

/**
 * Navigation between the assets of a publication: each asset has its URL.
 * Opening one pushes a history entry, moving to the next one replaces it,
 * so that the back button closes the viewer.
 */
export function useAssetRoute(publication: Publication) {
    const router = useRouter();

    const open = useCallback(
        (asset: Asset) =>
            router.push(routes.publicationAsset(publication, asset.id), {
                scroll: false,
            }),
        [router, publication]
    );
    const move = useCallback(
        (asset: Asset) =>
            router.replace(routes.publicationAsset(publication, asset.id), {
                scroll: false,
            }),
        [router, publication]
    );
    const close = useCallback(
        () =>
            router.push(routes.publication(publication), {
                scroll: false,
            }),
        [router, publication]
    );

    return {open, move, close};
}
