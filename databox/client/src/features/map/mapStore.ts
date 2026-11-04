import {create} from 'zustand';

export type MapStyle = 'streets' | 'satellite';

export type MapView = {
    center: [number, number];
    zoom: number;
};

type MapPreferences = {
    /** Search slug of the geo point attribute placed on the map */
    field?: string;
    style: MapStyle;
    /** Last viewed area, restored when coming back to the map */
    view?: MapView;
};

type State = MapPreferences & {
    set: (patch: Partial<MapPreferences>) => void;
};

const storageKey = 'dbx.map';

const defaults: MapPreferences = {style: 'streets'};

function read(): MapPreferences {
    try {
        const raw = window.localStorage.getItem(storageKey);

        return raw
            ? {...defaults, ...(JSON.parse(raw) as MapPreferences)}
            : defaults;
    } catch {
        return defaults;
    }
}

/**
 * How the map of the results is set up, remembered on this browser: the
 * viewed area changes too often for the user preferences.
 */
export const useMapStore = create<State>(set => ({
    ...(typeof window !== 'undefined' ? read() : defaults),
    set: patch =>
        set(s => {
            const next: MapPreferences = {
                field: s.field,
                style: s.style,
                view: s.view,
                ...patch,
            };
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(next));
            } catch {
                // Storage unavailable (private mode…): for this page only
            }

            return next;
        }),
}));
