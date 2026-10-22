import {useMemo} from 'react';
import {AttributeType} from '@/types/api';
import {useNarrowDefinitions} from '@/features/attributes/useAttributeDefinitionList';

export type GeoField = {
    /** AQL field of the conditions */
    slug: string;
    /** Attribute key of the clusters request (`location_geo-point_s`) */
    searchSlug: string;
    label: string;
};

const GEO_FILTERS = {
    type: AttributeType.GeoPoint,
    searchable: true,
    enabled: true,
};

/**
 * The geo point attributes the user can search, once each: an attribute
 * shared by several workspaces is the same field on the map.
 */
export function useGeoFields(): {fields: GeoField[]; loaded: boolean} {
    const {definitions, loaded} = useNarrowDefinitions(GEO_FILTERS);

    const fields = useMemo(() => {
        const bySlug = new Map<string, GeoField>();
        for (const d of definitions) {
            if (
                d.type !== AttributeType.GeoPoint ||
                !d.searchable ||
                !d.enabled ||
                bySlug.has(d.searchSlug)
            ) {
                continue;
            }
            bySlug.set(d.searchSlug, {
                slug: d.slug,
                searchSlug: d.searchSlug,
                label: d.displayName || d.name,
            });
        }

        return [...bySlug.values()].sort((a, b) =>
            a.label.localeCompare(b.label)
        );
    }, [definitions]);

    return {fields, loaded};
}
