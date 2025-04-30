import {create} from 'zustand';
import {AttributeList} from '../types';
import {
    addToAttributeList,
    AttributeListAssetInput,
    deleteAttributeList,
    getAttributeList,
    GetAttributeListOptions,
    getAttributeLists,
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
    addAttributeList: (basket: AttributeList) => void;
    updateAttributeList: (data: AttributeList) => void;
    deleteAttributeList: (id: string) => void;
    addToCurrent: (assets: AttributeListAssetInput[]) => void;
    removeFromAttributeList: (basketId: string, itemIds: string[]) => void;
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
            const basket = await getAttributeList(data.id);
            set({
                current: basket,
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

    addAttributeList(basket) {
        set(state => ({
            lists: [basket].concat(state.lists),
        }));
    },

    deleteAttributeList: async id => {
        await deleteAttributeList(id);

        set(state => ({
            lists: state.lists.filter(b => b.id !== id),
            current: state.current?.id === id ? undefined : state.current,
        }));
    },

    addToCurrent: async assets => {
        const current = getState().current;

        const currentId = current?.id;
        const count = assets.length;

        if (current && current.assetCount !== undefined) {
            set({
                current: {
                    ...current,
                    assetCount: current.assetCount + count,
                },
            });
        }

        try {
            const basket = await addToAttributeList(currentId, {
                assets,
            });
            set(state => ({
                current: basket,
                lists: state.lists.some(b => b.id === basket.id)
                    ? state.lists
                    : state.lists.concat([basket]),
            }));
        } catch (e: any) {
            if (current) {
                set(state => {
                    if (state.current?.id === current.id) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                assetCount:
                                    curr.assetCount !== undefined
                                        ? Math.max(0, curr.assetCount! - count)
                                        : undefined,
                            },
                        };
                    }

                    return state;
                });
            }
        }
    },

    removeFromAttributeList: async (basketId, itemIds) => {
        let current: AttributeList | undefined = getState().current;
        if (current && current.id !== basketId) {
            current = undefined;
        }
        const count = itemIds.length;

        if (current && current.assetCount !== undefined) {
            set({
                current: {
                    ...current,
                    assetCount: Math.max(0, current.assetCount - count),
                },
            });
        }

        try {
            const basket = await removeFromAttributeList(basketId, itemIds);
            set(state => ({
                lists: state.lists.some(b => b.id === basket.id)
                    ? state.lists
                    : state.lists.concat([basket]),
            }));
        } catch (e: any) {
            if (current) {
                set(state => {
                    if (state.current?.id === current!.id) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                assetCount:
                                    curr.assetCount !== undefined
                                        ? curr.assetCount! + count
                                        : undefined,
                            },
                        };
                    }

                    return state;
                });
            }
        }
    },
}));
