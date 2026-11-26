import {create} from 'zustand';
import type {Basket} from '@/types/api';
import {
    addToBasket,
    archiveBasket,
    deleteBasket,
    getBaskets,
    removeFromBasket,
    unarchiveBasket,
} from '@/lib/api/misc';
import {BasketEvent, EventCategory, track} from '@/lib/analytics/events';

type State = {
    baskets: Basket[];
    current: Basket | undefined;
    loaded: boolean;
    loading: boolean;
    /** Why the last load failed, cleared by the next one */
    error?: string;
    next?: string;
    includeArchived: boolean;
    query: string;
    load: (options?: {force?: boolean}) => Promise<void>;
    /** Never rejects: a failure is kept in `error` */
    loadMore: () => Promise<void>;
    setQuery: (query: string) => void;
    setIncludeArchived: (value: boolean) => void;
    setCurrent: (basket: Basket | undefined) => void;
    upsert: (basket: Basket) => void;
    remove: (id: string) => Promise<void>;
    archive: (id: string, archived: boolean) => Promise<void>;
    addToCurrent: (assetIds: string[]) => Promise<Basket>;
    removeItems: (basketId: string, itemIds: string[]) => Promise<void>;
};

const currentKey = 'dbx.basket.current';

function errorMessage(e: unknown): string {
    return e instanceof Error ? e.message : String(e);
}

export const useBasketStore = create<State>((set, get) => ({
    baskets: [],
    current: undefined,
    loaded: false,
    loading: false,
    includeArchived: false,
    query: '',

    load: async ({force} = {}) => {
        if ((get().loaded && !force) || get().loading) {
            return;
        }
        set({loading: true, error: undefined});
        try {
            const {query, includeArchived} = get();
            const page = await getBaskets({
                query: query || undefined,
                includeArchived: includeArchived || undefined,
            });
            const storedId =
                typeof window !== 'undefined'
                    ? localStorage.getItem(currentKey)
                    : null;
            const editable = page.items.filter(b => b.capabilities.edit);
            set(s => ({
                baskets: page.items,
                next: page.next,
                loaded: true,
                current:
                    s.current ??
                    page.items.find(b => b.id === storedId) ??
                    (editable.length === 1 ? editable[0] : undefined),
            }));
        } catch (e: unknown) {
            // Called fire-and-forget from effects: the panel shows the failure
            set({error: errorMessage(e)});
        } finally {
            set({loading: false});
        }
    },

    loadMore: async () => {
        const {next, loading} = get();
        if (!next || loading) {
            return;
        }
        set({loading: true, error: undefined});
        try {
            const page = await getBaskets({url: next});
            set(s => ({
                baskets: [...s.baskets, ...page.items],
                next: page.next,
            }));
        } catch (e: unknown) {
            set({error: errorMessage(e)});
        } finally {
            set({loading: false});
        }
    },

    setQuery: query => {
        set({query, loaded: false});
        void get().load({force: true});
    },

    setIncludeArchived: value => {
        set({includeArchived: value, loaded: false});
        void get().load({force: true});
    },

    setCurrent: basket => {
        set({current: basket});
        try {
            if (basket) {
                localStorage.setItem(currentKey, basket.id);
            } else {
                localStorage.removeItem(currentKey);
            }
        } catch {
            // ignore
        }
    },

    upsert: basket =>
        set(s => ({
            baskets: s.baskets.some(b => b.id === basket.id)
                ? s.baskets.map(b => (b.id === basket.id ? basket : b))
                : [basket, ...s.baskets],
            current: s.current?.id === basket.id ? basket : s.current,
        })),

    remove: async id => {
        await deleteBasket(id);
        track(EventCategory.Basket, BasketEvent.Delete);
        set(s => ({baskets: s.baskets.filter(b => b.id !== id)}));
        if (get().current?.id === id) {
            get().setCurrent(undefined);
        }
    },

    archive: async (id, archived) => {
        const basket = archived
            ? await archiveBasket(id)
            : await unarchiveBasket(id);
        track(
            EventCategory.Basket,
            archived ? BasketEvent.Archive : BasketEvent.Unarchive
        );
        if (archived && !get().includeArchived) {
            set(s => ({baskets: s.baskets.filter(b => b.id !== id)}));
            if (get().current?.id === id) {
                get().setCurrent(undefined);
            }
        } else {
            get().upsert(basket);
        }
    },

    addToCurrent: async assetIds => {
        const basket = await addToBasket(get().current?.id, assetIds);
        track(EventCategory.Basket, BasketEvent.Add, {value: assetIds.length});
        get().upsert(basket);
        if (!get().current) {
            get().setCurrent(basket);
        }

        return basket;
    },

    removeItems: async (basketId, itemIds) => {
        const basket = await removeFromBasket(basketId, itemIds);
        track(EventCategory.Basket, BasketEvent.Remove, {
            value: itemIds.length,
        });
        get().upsert(basket);
    },
}));
