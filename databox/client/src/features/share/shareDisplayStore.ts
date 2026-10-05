import {create} from 'zustand';
import type {LayoutMode} from '@/features/preferences/store';

type ShareDisplay = {
    layout: LayoutMode;
    thumbSize: number;
};

type State = ShareDisplay & {
    set: (patch: Partial<ShareDisplay>) => void;
};

const storageKey = 'dbx.share.display';

const defaults: ShareDisplay = {layout: 'grid', thumbSize: 240};

function read(): ShareDisplay {
    try {
        const raw = window.localStorage.getItem(storageKey);

        return raw
            ? {...defaults, ...(JSON.parse(raw) as ShareDisplay)}
            : defaults;
    } catch {
        return defaults;
    }
}

/**
 * How the visitor of a public share lays out its assets. Visitors are mostly
 * anonymous: remembered on this browser, not in the user preferences.
 */
export const useShareDisplayStore = create<State>(set => ({
    ...(typeof window !== 'undefined' ? read() : defaults),
    set: patch =>
        set(s => {
            const next = {layout: s.layout, thumbSize: s.thumbSize, ...patch};
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(next));
            } catch {
                // Storage unavailable (private mode…): for this page only
            }

            return next;
        }),
}));
