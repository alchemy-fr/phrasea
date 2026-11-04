'use client';

import {useEffect, useRef} from 'react';
import {useQuery} from '@tanstack/react-query';
import {
    getAsset,
    getPublication,
    logAssetView,
    logPublicationView,
    publicationKeys,
    assetKeys,
} from '@/lib/api/publications';

export function usePublication(id: string) {
    return useQuery({
        queryKey: publicationKeys.detail(id),
        queryFn: () => getPublication(id),
    });
}

export function useEmbeddedAsset(id: string) {
    return useQuery({
        queryKey: assetKeys.detail(id),
        queryFn: () => getAsset(id),
    });
}

/**
 * Logs one view of the publication per visit, once its content is shown:
 * neither the password screen nor a reload of the data count.
 */
export function usePublicationViewLog(
    publicationId: string | undefined,
    authorized: boolean
): void {
    const logged = useRef<string | undefined>(undefined);

    useEffect(() => {
        if (publicationId && authorized && logged.current !== publicationId) {
            logged.current = publicationId;
            logPublicationView(publicationId);
        }
    }, [publicationId, authorized]);
}

/**
 * Logs the view of the asset displayed, once it stayed a second on screen:
 * browsing quickly through a publication logs the assets looked at only.
 */
export function useAssetViewLog(assetId: string | undefined): void {
    useEffect(() => {
        if (!assetId) {
            return;
        }
        const timeout = setTimeout(() => logAssetView(assetId), 1000);

        return () => clearTimeout(timeout);
    }, [assetId]);
}
