import {create} from 'zustand';
import {Basket} from '../types.ts';
import {addToBasket, deleteBasket, getBasket, GetBasketOptions, getBaskets} from '../api/basket.ts';

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
    addToCurrent: (ids: string[]) => void;
    setCurrent: (data: Basket | undefined) => Promise<void>;
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
            console.log('data.next', data.next);

            set({
                baskets: data.result,
                total: data.total,
                loading: false,
            });
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
            })
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
        }));
    },

    addToCurrent: async (ids) => {
        const current = getState().current;
        if (!current) {
            alert('No Basket selected');
            return;
        }

        const currentId = current.id;
        const c = ids.length;

        set({
            current: {
                ...current,
                assetCount: current.assetCount! + c,
            },
        });

        try {
            const basket = await addToBasket(currentId, ids);
            set({
                current: basket,
            });
        } catch (e: any) {
            set(state => {
                if (state.current!.id === currentId) {
                    return {
                        current: {
                            ...state.current!,
                            assetCount: state.current!.assetCount! - c,
                        },
                    };
                }

                return state;
            });
        }
    }
}));
