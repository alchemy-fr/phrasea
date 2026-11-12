import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import type {Asset} from '@/types/api';
import {storeShareToken} from '@/lib/api/shareTokens';
import {getEmbeddedAsset} from './usePublication';

const api = vi.hoisted(() => ({getAsset: vi.fn()}));
vi.mock('@/lib/api/publications', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...api,
}));

function asset(authorized: boolean): Asset {
    return {
        id: 'a1',
        publication: {id: 'p1', slug: 'my-slug', authorized},
    } as Asset;
}

describe('getEmbeddedAsset', () => {
    beforeEach(() => {
        api.getAsset.mockReset();
    });

    afterEach(() => {
        document.cookie = 'shareTokens_p1=; path=/; max-age=0';
        document.cookie = 'shareTokens_my-slug=; path=/; max-age=0';
    });

    it('asks again with the share links of the publication', async () => {
        storeShareToken({id: 'p1', slug: 'my-slug'}, 'aaa');
        api.getAsset
            .mockResolvedValueOnce(asset(false))
            .mockResolvedValueOnce(asset(true));

        expect(await getEmbeddedAsset('a1')).toEqual(asset(true));
        expect(api.getAsset).toHaveBeenNthCalledWith(1, 'a1');
        expect(api.getAsset).toHaveBeenNthCalledWith(
            2,
            'a1',
            asset(false).publication
        );
    });

    it('does not ask again without any share link', async () => {
        api.getAsset.mockResolvedValueOnce(asset(false));

        expect(await getEmbeddedAsset('a1')).toEqual(asset(false));
        expect(api.getAsset).toHaveBeenCalledTimes(1);
    });

    it('does not ask again once authorized', async () => {
        storeShareToken({id: 'p1'}, 'aaa');
        api.getAsset.mockResolvedValueOnce(asset(true));

        expect(await getEmbeddedAsset('a1')).toEqual(asset(true));
        expect(api.getAsset).toHaveBeenCalledTimes(1);
    });
});
