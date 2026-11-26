import {beforeEach, describe, expect, it, vi} from 'vitest';
import {useBasketStore} from './basketStore';

const {api} = vi.hoisted(() => ({
    api: {getBaskets: vi.fn()},
}));

vi.mock('@/lib/api/misc', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/misc')>()),
    getBaskets: api.getBaskets,
}));

// Node ships its own (broken, file-backed) localStorage that shadows jsdom's
Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {getItem: () => null, setItem: () => undefined},
});

describe('useBasketStore.load', () => {
    beforeEach(() => {
        api.getBaskets.mockReset();
        useBasketStore.setState({
            baskets: [],
            loaded: false,
            loading: false,
            error: undefined,
        });
    });

    it('keeps the failure until the next load', async () => {
        api.getBaskets.mockRejectedValueOnce(
            new Error('Internal Server Error')
        );
        await useBasketStore.getState().load();

        expect(useBasketStore.getState()).toMatchObject({
            error: 'Internal Server Error',
            loaded: false,
            loading: false,
        });

        api.getBaskets.mockResolvedValueOnce({items: [], total: 0});
        await useBasketStore.getState().load({force: true});

        expect(useBasketStore.getState()).toMatchObject({
            error: undefined,
            loaded: true,
        });
    });
});

describe('useBasketStore.loadMore', () => {
    beforeEach(() => {
        api.getBaskets.mockReset();
        useBasketStore.setState({
            baskets: [{id: 'b1'} as never],
            next: '/baskets?page=2',
            loaded: true,
            loading: false,
            loadingMore: false,
            query: '',
            includeArchived: false,
            error: undefined,
        });
    });

    it('keeps the failure and lets the next page be asked again', async () => {
        api.getBaskets.mockRejectedValueOnce(new Error('Bad Gateway'));
        await useBasketStore.getState().loadMore();

        expect(useBasketStore.getState()).toMatchObject({
            error: 'Bad Gateway',
            loadingMore: false,
            next: '/baskets?page=2',
        });

        api.getBaskets.mockResolvedValueOnce({
            items: [{id: 'b2'}],
            total: 2,
        });
        await useBasketStore.getState().loadMore();

        expect(api.getBaskets).toHaveBeenLastCalledWith({
            url: '/baskets?page=2',
        });
        expect(useBasketStore.getState()).toMatchObject({
            error: undefined,
            loadingMore: false,
            next: undefined,
        });
        expect(useBasketStore.getState().baskets.map(b => b.id)).toEqual([
            'b1',
            'b2',
        ]);
    });

    it('lets a new filter reload the list while a next page is read, and drops that page', async () => {
        let resolvePage!: (page: unknown) => void;
        api.getBaskets.mockImplementationOnce(
            () => new Promise(resolve => (resolvePage = resolve))
        );
        const pending = useBasketStore.getState().loadMore();

        api.getBaskets.mockResolvedValueOnce({
            items: [{id: 'f1', capabilities: {edit: true}}],
            total: 1,
        });
        useBasketStore.getState().setQuery('foo');
        await vi.waitFor(() =>
            expect(useBasketStore.getState().loaded).toBe(true)
        );
        expect(api.getBaskets).toHaveBeenLastCalledWith({query: 'foo'});

        resolvePage({items: [{id: 'b2'}], total: 2});
        await pending;

        expect(useBasketStore.getState()).toMatchObject({
            loadingMore: false,
            next: undefined,
        });
        expect(useBasketStore.getState().baskets.map(b => b.id)).toEqual([
            'f1',
        ]);
    });
});
