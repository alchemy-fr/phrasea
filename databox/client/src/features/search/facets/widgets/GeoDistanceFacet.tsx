'use client';

import {useTranslation} from 'react-i18next';
import {MapPinIcon} from 'lucide-react';
import type {FacetWidgetProps} from '../FacetsPanel';
import {formatNumber} from '@/lib/utils/format';

/**
 * Distance rings around the user position. The rings are listed with their
 * document count (the map rendering is provided by the map module when
 * available).
 */
export function GeoDistanceFacet({facet}: FacetWidgetProps) {
    const {t, i18n} = useTranslation();
    const position = facet.meta.position;
    const rings = facet.buckets.filter(b => (b.to ?? 0) > 0 && b.doc_count > 0);

    return (
        <div className="space-y-1 px-1 text-sm">
            {position ? (
                <div className="flex items-center gap-1 text-xs text-muted-foreground">
                    <MapPinIcon className="size-3.5" />
                    {position[0].toFixed(4)}, {position[1].toFixed(4)}
                </div>
            ) : null}
            {rings.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {t('facets.geo.empty', 'No asset around this position')}
                </p>
            ) : (
                <ul className="space-y-0.5">
                    {rings.map(b => (
                        <li
                            key={String(b.key)}
                            className="flex items-center justify-between rounded px-1.5 py-1"
                        >
                            <span>
                                {t('facets.geo.within', 'Within {{distance}}', {
                                    distance: formatDistance(b.to!),
                                })}
                            </span>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatNumber(b.doc_count, i18n.language)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function formatDistance(meters: number): string {
    return meters >= 1000
        ? `${Math.round(meters / 100) / 10} km`
        : `${Math.round(meters)} m`;
}
