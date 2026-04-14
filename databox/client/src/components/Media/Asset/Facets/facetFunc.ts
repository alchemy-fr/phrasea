import {UpdatePreference} from '../../../../store/userPreferencesStore.ts';
import {FacetPreference} from './facetTypes.ts';

export function hideFacet(updatePreference: UpdatePreference, name: string) {
    updatePreference('facets', prev => {
        if (prev?.some(p => p.name === name)) {
            return prev.map(p =>
                p.name === name
                    ? {
                          ...p,
                          hidden: true,
                      }
                    : p
            );
        }

        return (prev ?? []).concat([
            {
                name,
                hidden: true,
            },
        ]);
    });
}

export function togglePinFacet(
    updatePreference: UpdatePreference,
    name: string
) {
    const getNextPos = (prev: FacetPreference[] | undefined) => {
        return Math.max(-1, ...(prev ?? []).map(p => p.order ?? -1)) + 1;
    };

    updatePreference('facets', prev => {
        const existing = prev?.find(p => p.name === name);
        if (existing) {
            if (existing.hidden) {
                return prev!.map(p =>
                    p.name === name
                        ? {
                              ...p,
                              order: getNextPos(prev),
                              hidden: undefined,
                          }
                        : p
                );
            }

            return prev!.filter(p => p.name !== name);
        }

        return (prev ?? []).concat([
            {
                name,
                order: getNextPos(prev),
            },
        ]);
    });
}

export function unhideFacet(updatePreference: UpdatePreference, name: string) {
    updatePreference('facets', prev => prev?.filter(fp => fp.name !== name));
}

export function createUndo(
    updatePreference: UpdatePreference,
    facetsPref: FacetPreference[],
    name: string
) {
    const previousPref = facetsPref.find(p => p.name === name);
    return () => {
        if (previousPref) {
            updatePreference('facets', prev => {
                return (prev ?? []).map(p =>
                    p.name === name ? previousPref : p
                );
            });
        } else {
            unhideFacet(updatePreference, name);
        }
    };
}
