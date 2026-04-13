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
    updatePreference('facets', prev => {
        if (prev?.some(p => p.name === name)) {
            return prev.filter(p => p.name !== name);
        }

        return (prev ?? []).concat([
            {
                name,
                order:
                    Math.max(-1, ...(prev ?? []).map(p => p.order ?? -1)) + 1,
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
