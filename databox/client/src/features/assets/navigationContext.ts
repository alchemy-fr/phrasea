import {create} from 'zustand';

type NavigationContext = {
    ids: string[];
    /** callback to load more items when reaching the end */
    loadMore?: () => Promise<string[]>;
};

type State = {
    context: NavigationContext | undefined;
    set: (ctx: NavigationContext | undefined) => void;
};

/**
 * Ordered list of asset ids of the list the viewer was opened from.
 */
export const useNavigationContextStore = create<State>(set => ({
    context: undefined,
    set: context => set({context}),
}));
