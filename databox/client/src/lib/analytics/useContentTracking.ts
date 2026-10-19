'use client';

import {useEffect, useRef} from 'react';
import type {Asset} from '@/types/api';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {
    isMediaTrackingEnabled,
    trackContentImpression,
    trackContentInteraction,
} from './matomo';

/** An asset as Matomo knows it: content piece / media resource, and name */
export type ContentTracking = {
    id: string;
    title?: string;
};

type TrackedAsset = Pick<
    Asset,
    'id' | 'name' | 'trackingId' | 'resolvedTrackingId'
>;

/**
 * The tracking id read back by the API (`MatomoManager`): same fallbacks as
 * `Asset::getResolvedTrackingId()`.
 */
export function getContentTracking(asset: TrackedAsset): ContentTracking {
    return {
        id: asset.resolvedTrackingId ?? asset.trackingId ?? asset.id,
        title: asset.name,
    };
}

function contentName({id, title}: ContentTracking): string {
    return title || id;
}

/**
 * Tracks the display of an asset, once per asset, when the type of the
 * displayed file is known. A video is left to MediaAnalytics when the plugin
 * is enabled.
 */
export function useContentImpression(
    asset: TrackedAsset | undefined,
    fileType: string | undefined
): void {
    const tracked = useRef<string | undefined>(undefined);
    const assetId = asset?.id;
    const tracking = asset ? getContentTracking(asset) : undefined;
    const piece = tracking?.id;
    const name = tracking ? contentName(tracking) : undefined;

    useEffect(() => {
        if (!assetId || !piece || !name || !fileType) {
            return;
        }
        if (tracked.current === assetId) {
            return;
        }
        tracked.current = assetId;
        if (
            getFileKind(fileType) === FileKind.Video &&
            isMediaTrackingEnabled()
        ) {
            return;
        }
        trackContentImpression(name, piece);
    }, [assetId, piece, name, fileType]);
}

/** A click on the media of an asset (image, PDF page) */
export function contentClick(tracking: ContentTracking): void {
    trackContentInteraction('click', contentName(tracking), tracking.id);
}
