import {create} from 'zustand';

export type LeftPanelTab = 'facets' | 'tree' | 'baskets';

type LayoutState = {
    leftPanelOpen: boolean;
    leftPanelTab: LeftPanelTab;
    toggleLeftPanel: (open?: boolean) => void;
    setLeftPanelTab: (tab: LeftPanelTab) => void;
};

export const useLayoutStore = create<LayoutState>(set => ({
    leftPanelOpen:
        typeof window === 'undefined' ? true : window.innerWidth >= 900,
    leftPanelTab: 'facets',
    toggleLeftPanel: open =>
        set(s => ({leftPanelOpen: open ?? !s.leftPanelOpen})),
    setLeftPanelTab: tab => set({leftPanelTab: tab, leftPanelOpen: true}),
}));
