import {useEffect, useId} from 'react';
import {create} from 'zustand';

export type LeftPanelTab = 'facets' | 'tree' | 'baskets';

/** One step of the trail displayed in the top bar */
export type PageTrailEntry = {
    id: string;
    label: string;
    /** Makes the step clickable once another one is stacked over it */
    href?: string;
};

type LayoutState = {
    leftPanelOpen: boolean;
    leftPanelTab: LeftPanelTab;
    /**
     * Screens opened over the main view (asset, basket, batch editor…), in
     * the order they were stacked: the top bar tells where the user is and
     * how to get back.
     */
    pageTrail: PageTrailEntry[];
    toggleLeftPanel: (open?: boolean) => void;
    setLeftPanelTab: (tab: LeftPanelTab) => void;
    setTrailEntry: (
        entry: PageTrailEntry | {id: string; label?: undefined; href?: string}
    ) => void;
};

const TAB_KEY = 'dbx.left-panel-tab';

function readTab(): LeftPanelTab {
    try {
        const v = window.localStorage.getItem(TAB_KEY);

        return v === 'tree' || v === 'baskets' ? v : 'facets';
    } catch {
        return 'facets';
    }
}

export const useLayoutStore = create<LayoutState>(set => ({
    leftPanelOpen:
        typeof window === 'undefined' ? true : window.innerWidth >= 900,
    leftPanelTab: typeof window === 'undefined' ? 'facets' : readTab(),
    pageTrail: [],
    toggleLeftPanel: open =>
        set(s => ({leftPanelOpen: open ?? !s.leftPanelOpen})),
    setLeftPanelTab: tab => {
        try {
            window.localStorage.setItem(TAB_KEY, tab);
        } catch {
            // storage unavailable
        }
        set({leftPanelTab: tab, leftPanelOpen: true});
    },

    // Updating a step keeps its position: a label resolving later (the asset
    // name) must not move the screen to the top of the trail.
    setTrailEntry: entry =>
        set(s => {
            const index = s.pageTrail.findIndex(e => e.id === entry.id);
            if (!entry.label) {
                return index < 0
                    ? s
                    : {pageTrail: s.pageTrail.filter((_, i) => i !== index)};
            }
            if (index < 0) {
                return {pageTrail: [...s.pageTrail, entry]};
            }
            const pageTrail = [...s.pageTrail];
            pageTrail[index] = entry;

            return {pageTrail};
        }),
}));

/**
 * Publishes the screen the user is on to the top bar, for as long as it is
 * mounted. A screen opened over another one (the asset viewer over a basket)
 * stacks on top of it and restores it when closed.
 */
export function usePageTrail(label: string | undefined, href?: string): void {
    const id = useId();
    const setTrailEntry = useLayoutStore(s => s.setTrailEntry);

    useEffect(() => {
        setTrailEntry({id, label, href});

        return () => setTrailEntry({id});
    }, [id, label, href, setTrailEntry]);
}
