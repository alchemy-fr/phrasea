import {create} from 'zustand';
import {Basket} from '../types.ts';
import {deleteBasket, GetBasketOptions, getBaskets} from '../api/basket.ts';

type State = {
    baskets: Basket[];
    loading: boolean;
    loadingMore: boolean;
    total?: number;
    load: (params?: GetBasketOptions) => Promise<void>;
    loadMore: () => Promise<void>;
    addBasket: (basket: Basket) => void;
    updateBasket: (data: Basket) => void;
    deleteBasket: (id: string) => void;
};

export const useBasketStore = create<State>((set, getState) => ({
    loadingMore: false,
    loading: false,
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
    }
}));
