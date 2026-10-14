'use client';

import dynamic from 'next/dynamic';
import {useConfig} from '@/lib/config/ConfigProvider';
import type {LngLat} from './geo';

// Mapbox weighs about 1 MB and needs a browser: loaded with the first map shown
const GeoPointMap = dynamic(() => import('./GeoPointMap'), {
    ssr: false,
    loading: () => (
        <div className="h-48 w-full max-w-sm animate-pulse rounded-md bg-muted" />
    ),
});

/**
 * The "map" format of a geo point attribute. Without a Mapbox token, the
 * coordinates are shown instead.
 */
export function GeoPointMapValue({
    point,
    fallback,
}: {
    point: LngLat;
    fallback: string;
}) {
    const token = useConfig().mapbox?.token;

    if (!token) {
        return <>{fallback}</>;
    }

    return <GeoPointMap token={token} point={point} />;
}
