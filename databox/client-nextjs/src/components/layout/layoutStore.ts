import {create} from 'zustand';

export type LeftPanelTab = 'facets' | 'tree' | 'baskets';

type LayoutState = {
    leftPanelOpen: boolean;
    leftPanelTab: LeftPanelTab;
    toggleLeftPanel: (open?: boolean) => void;
    setLeftPanelTab: (tab: LeftPanelTab) => void;
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
}));
