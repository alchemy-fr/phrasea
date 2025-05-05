import {create} from 'zustand';
import {AttributeList} from '../types';
import {
    addToAttributeList,
    deleteAttributeList,
    getAttributeList,
    GetAttributeListOptions,
    getAttributeLists, putAttributeList,
    removeFromAttributeList,
} from '../api/attributeList';

type State = {
    lists: AttributeList[];
    current: AttributeList | undefined;
    nextUrl?: string | undefined;
    loaded: boolean;
    loading: boolean;
    loadingCurrent: boolean;
    loadingMore: boolean;
    total?: number;
    hasMore: () => boolean;
    load: (params?: GetAttributeListOptions, force?: boolean) => Promise<void>;
    loadMore: () => Promise<void>;
    addAttributeList: (list: AttributeList) => void;
    updateAttributeList: (data: AttributeList) => void;
    deleteAttributeList: (id: string) => void;
    addToCurrent: (definitions: string[]) => void;
    addToList: (listId: string | undefined, definitions: string[]) => void;
    replaceList: (listId: string, definitions: string[]) => void;
    toggleDefinition: (definition: string) => void;
    removeFromAttributeList: (listId: string, definitions: string[]) => void;
    setCurrent: (data: AttributeList | undefined) => Promise<void>;
    shouldSelectAttributeList: () => boolean;
};

export const useAttributeListStore = create<State>((set, getState) => ({
    loadingMore: false,
    loaded: false,
    loading: false,
    loadingCurrent: false,
    current: undefined,
    lists: [],

    load: async (params, force) => {
        if (getState().loaded && !force) {
            return;
        }

        set({
            loading: true,
        });

        try {
            const data = await getAttributeLists(undefined, params);

            set(state => ({
                lists: data.result,
                total: data.total,
                loading: false,
                loaded: true,
                current: data.total === 1 ? data.result[0] : state.current,
                nextUrl: data.next || undefined,
            }));
        } catch (e: any) {
            set({loading: false});
            throw e;
        }
    },

    hasMore() {
        return !!getState().nextUrl;
    },

    setCurrent: async data => {
        if (!data) {
            set({
                current: undefined,
                loadingCurrent: false,
            });

            return;
        }

        set({
            current: data,
            loadingCurrent: true,
        });

        try {
            const list = await getAttributeList(data.id);
            set({
                current: list,
                loadingCurrent: false,
            });
        } catch (e: any) {
            set({
                loadingCurrent: false,
            });
        }
    },

    shouldSelectAttributeList: () => {
        const {current, loading, lists} = getState();

        if (current) {
            return false;
        }

        if (loading) {
            return true;
        }

        return lists.length > 1;
    },

    updateAttributeList: data => {
        set(state => ({
            lists: state.lists.map(b => {
                if (b.id === data.id) {
                    return {
                        ...b,
                        ...data,
                    };
                }

                return b;
            }),
            current: state.current?.id === data.id ? data : state.current,
        }));
    },

    loadMore: async () => {
        const nextUrl = getState().nextUrl;
        if (!nextUrl) {
            return;
        }

        set({loadingMore: true});
        try {
            const data = await getAttributeLists(nextUrl);

            set(state => ({
                lists: state.lists.concat(data.result),
                total: data.total,
                loadingMore: false,
                nextUrl: data.next || undefined,
            }));
        } catch (e: any) {
            set({loadingMore: false});

            throw e;
        }
    },

    addAttributeList(list) {
        set(state => ({
            lists: [list].concat(state.lists),
        }));
    },

    deleteAttributeList: async id => {
        await deleteAttributeList(id);

        set(state => ({
            lists: state.lists.filter(b => b.id !== id),
            current: state.current?.id === id ? undefined : state.current,
        }));
    },

    toggleDefinition: definition => {
        const state = getState();
        const current = state.current;

        if (current) {
            if (current.definitions!.slice().includes(definition)) {
                state.removeFromAttributeList(current.id, [definition]);

                return;
            }
        }

        state.addToCurrent([definition]);
    },

    addToList: async (listId, definitions) => {
        try {
            const list = await addToAttributeList(listId, {
                definitions,
            });
            set(state => ({
                current: list,
                lists: state.lists.some(b => b.id === list.id)
                    ? state.lists
                    : state.lists.concat([list]),
            }));
        } catch (e: any) {
            if (listId) {
                set(state => {
                    if (state.current?.id === listId) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                definitions: curr.definitions,
                            },
                        };
                    }

                    return state;
                });
            }
        }
    },

    addToCurrent: async definitions => {
        const state = getState();
        state.addToList(state.current?.id, definitions);
    },

    replaceList: async (listId, definitions) => {
        const list = await putAttributeList(listId, {
            definitions,
        });

        set(p => ({
            lists: p.lists.map(b => {
                if (b.id === listId) {
                    return list;
                }

                return b;
            }),
            current: p.current?.id === listId ? list : p.current,
        }));
    },

    removeFromAttributeList: async (listId, definitions) => {
        let current: AttributeList | undefined = getState().current;
        if (current && current.id !== listId) {
            current = undefined;
        }

        if (current && current.definitions !== undefined) {
            set({
                current: {
                    ...current,
                    definitions: current.definitions.filter(
                        d => !definitions.includes(d)
                    ),
                },
            });
        }

        try {
            const list = await removeFromAttributeList(listId, definitions);
            set(state => ({
                lists: state.lists.some(b => b.id === list.id)
                    ? state.lists
                    : state.lists.concat([list]),
            }));
        } catch (e: any) {
            if (current) {
                set(state => {
                    if (state.current?.id === current!.id) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                definitions: curr.definitions
                                    ? curr.definitions.concat(definitions)
                                    : [],
                            },
                        };
                    }

                    return state;
                });
            }
        }
    },
}));
