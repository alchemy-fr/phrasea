import {create} from 'zustand';
import {SavedSearch} from '../types';
import {
    deleteSavedSearch,
    getSavedSearch,
    getSavedSearches,
    GetSavedSearchOptions,
} from '../api/savedSearch.ts';

type State = {
    searches: SavedSearch[];
    nextUrl?: string | undefined;
    loaded: boolean;
    loading: boolean;
    loadingMore: boolean;
    total?: number;
    hasMore: () => boolean;
    load: (params?: GetSavedSearchOptions, force?: boolean) => Promise<void>;
    loadMore: () => Promise<void>;
    add: (item: SavedSearch) => void;
    loadItem: (id: string) => Promise<SavedSearch>;
    updateItem: (data: SavedSearch) => void;
    deleteItem: (id: string) => void;
};

export const useSavedSearchStore = create<State>((set, getState) => ({
    loadingMore: false,
    loaded: false,
    loading: false,
    searches: [],

    load: async (params, force) => {
        if (getState().loaded && !force) {
            return;
        }

        set({
            loading: true,
        });

        try {
            const data = await getSavedSearches(undefined, params);

            set({
                searches: data.result,
                total: data.total,
                loading: false,
                loaded: true,
                nextUrl: data.next || undefined,
            });
        } catch (e: any) {
            set({loading: false});
            throw e;
        }
    },

    hasMore() {
        return !!getState().nextUrl;
    },

    updateItem: data => {
        set(state => ({
            searches: state.searches.map(b => {
                if (b.id === data.id) {
                    return {
                        ...b,
                        ...data,
                    };
                }

                return b;
            }),
        }));
    },

    loadMore: async () => {
        const nextUrl = getState().nextUrl;
        if (!nextUrl) {
            return;
        }

        set({loadingMore: true});
        try {
            const data = await getSavedSearches(nextUrl);

            set(state => ({
                searches: state.searches.concat(data.result),
                total: data.total,
                loadingMore: false,
                nextUrl: data.next || undefined,
            }));
        } catch (e: any) {
            set({loadingMore: false});

            throw e;
        }
    },

    add(list) {
        set(state => ({
            searches: [list].concat(state.searches),
        }));
    },

    deleteItem: async id => {
        await deleteSavedSearch(id);

        set(state => ({
            searches: state.searches.filter(b => b.id !== id),
        }));
    },

    loadItem: async (id: string) => {
        const list = await getSavedSearch(id!);
        set(state => {
            return {
                searches: replaceList(state.searches, list),
            };
        });

        return list;
    },
}));

function replaceList(prev: SavedSearch[], list: SavedSearch): SavedSearch[] {
    return prev.some(l => l.id === list.id)
        ? prev.map(l => (l.id === list.id ? list : l))
        : prev.concat([list]);
}
