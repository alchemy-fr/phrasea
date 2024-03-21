import {create} from 'zustand';
import {Basket} from '../types.ts';
import {
    addToBasket,
    BasketAssetInput,
    deleteBasket,
    getBasket,
    GetBasketOptions,
    getBaskets,
    removeFromBasket
} from '../api/basket.ts';

type State = {
    baskets: Basket[];
    current: Basket | undefined;
    loading: boolean;
    loadingCurrent: boolean;
    loadingMore: boolean;
    total?: number;
    load: (params?: GetBasketOptions) => Promise<void>;
    loadMore: () => Promise<void>;
    addBasket: (basket: Basket) => void;
    updateBasket: (data: Basket) => void;
    deleteBasket: (id: string) => void;
    addToCurrent: (assets: BasketAssetInput[]) => void;
    removeFromBasket: (basketId: string, itemIds: string[]) => void;
    setCurrent: (data: Basket | undefined) => Promise<void>;
    shouldSelectBasket: () => boolean;
};

export const useBasketStore = create<State>((set, getState) => ({
    loadingMore: false,
    loading: false,
    loadingCurrent: false,
    current: undefined,
    baskets: [],

    load: async (params) => {
        set({
            loading: false,
        });

        try {
            const data = await getBaskets(params);

            set(state => ({
                baskets: data.result,
                total: data.total,
                loading: false,
                current: data.total === 1 ? data.result[0] : state.current,
            }));
        } catch (e: any) {
            set({loadingMore: true});
            throw e;
        }
    },

    setCurrent: async (data) => {
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
            const basket = await getBasket(data.id);
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

    shouldSelectBasket: () => {
        const {current, loading, baskets} = getState();

        if (current) {
            return false;
        }

        if (loading) {
            return true;
        }

        return baskets.length > 1;
    },

    updateBasket: data => {
        set(state => ({
            baskets: state.baskets.map(b => {
                if (b.id === data.id) {
                    return {
                        ...b,
                        ...data,
                    };
                }

                return b;
            }),
            current: state.current?.id === data.id ? data : state.current,
        }))
    },

    loadMore: async () => {
        const pager = getState().baskets;
        if (!pager) {
            return;
        }

        set({loadingMore: true});
        try {
            const data = await getBaskets(); // TODO

            set(state => ({
                baskets: state.baskets.concat(data.result),
                total: data.total,
                loadingMore: false,
            }));
        } catch (e: any) {
            set({loadingMore: false});

            throw e;
        }
    },

    addBasket(basket) {
        set(state => ({
            baskets: [basket].concat(state.baskets),
        }));
    },

    deleteBasket: async (id) => {
        await deleteBasket(id);

        set(state => ({
            baskets: state.baskets.filter(b => b.id !== id),
            current: state.current?.id === id ? undefined : state.current,
        }));
    },

    addToCurrent: async (assets) => {
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
            const basket = await addToBasket(currentId, {
                assets,
            });
            set(state => ({
                current: basket,
                baskets: state.baskets.some(b => b.id === basket.id) ? state.baskets : state.baskets.concat([basket]),
            }));
        } catch (e: any) {
            if (current) {
                set(state => {
                    if (state.current?.id === current.id) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                assetCount: curr.assetCount !== undefined ? Math.max(0, curr.assetCount! - count) : undefined,
                            },
                        };
                    }

                    return state;
                });
            }
        }
    },

    removeFromBasket: async (basketId, itemIds) => {
        let current: Basket | undefined = getState().current;
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
            const basket = await removeFromBasket(basketId, itemIds);
            set(state => ({
                baskets: state.baskets.some(b => b.id === basket.id) ? state.baskets : state.baskets.concat([basket]),
            }));
        } catch (e: any) {
            if (current) {
                set(state => {
                    if (state.current?.id === current!.id) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                assetCount: curr.assetCount !== undefined ? curr.assetCount! + count : undefined,
                            },
                        };
                    }

                    return state;
                });
            }
        }
    }
}));
