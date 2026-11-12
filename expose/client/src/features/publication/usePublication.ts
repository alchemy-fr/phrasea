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
import {getShareTokens, type ShareTokenTarget} from '@/lib/api/shareTokens';
import type {Asset, Publication} from '@/types/api';

export function usePublication(id: string) {
    return useQuery({
        queryKey: publicationKeys.detail(id),
        queryFn: () => getPublication(id),
    });
}

/**
 * The asset of an embed, whose publication is unknown before the response:
 * asked again with the share links of that publication when they may open it.
 */
export async function getEmbeddedAsset(id: string): Promise<Asset> {
    const asset = await getAsset(id);
    const publication = asset.publication;
    if (
        publication &&
        !publication.authorized &&
        getShareTokens(publication).length > 0
    ) {
        return getAsset(id, publication);
    }

    return asset;
}

export function useEmbeddedAsset(id: string) {
    return useQuery({
        queryKey: assetKeys.detail(id),
        queryFn: () => getEmbeddedAsset(id),
    });
}

/**
 * Logs one view of the publication per visit, once its content is shown:
 * neither the password screen nor a reload of the data count.
 */
export function usePublicationViewLog(
    publication: Publication | undefined,
    authorized: boolean
): void {
    const logged = useRef<string | undefined>(undefined);

    useEffect(() => {
        if (publication && authorized && logged.current !== publication.id) {
            logged.current = publication.id;
            logPublicationView(publication);
        }
    }, [publication, authorized]);
}

/**
 * Logs the view of the asset displayed, once it stayed a second on screen:
 * browsing quickly through a publication logs the assets looked at only.
 */
export function useAssetViewLog(
    assetId: string | undefined,
    publication: ShareTokenTarget | undefined
): void {
    useEffect(() => {
        if (!assetId || !publication) {
            return;
        }
        const timeout = setTimeout(
            () => logAssetView(assetId, publication),
            1000
        );

        return () => clearTimeout(timeout);
    }, [assetId, publication]);
}
