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
