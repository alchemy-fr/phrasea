import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QueryClient} from '@tanstack/react-query';
import type {Asset, Basket, Workspace} from '@/types/api';
import type {CollectionNode} from '@/features/collections/collectionStore';

const api = vi.hoisted(() => ({
    addAssetsToCollection: vi.fn(() => Promise.resolve()),
    copyAssets: vi.fn(() => Promise.resolve()),
    moveAssets: vi.fn(() => Promise.resolve()),
    addToBasket: vi.fn(),
    moveCollection: vi.fn(() => Promise.resolve()),
}));
const toast = vi.hoisted(() => ({success: vi.fn(), error: vi.fn()}));
vi.mock('sonner', () => ({toast}));
vi.mock('@/lib/api/assets', () => ({
    addAssetsToCollection: api.addAssetsToCollection,
    copyAssets: api.copyAssets,
    moveAssets: api.moveAssets,
}));
vi.mock('@/lib/api/misc', () => ({addToBasket: api.addToBasket}));
vi.mock('@/lib/api/collections', () => ({moveCollection: api.moveCollection}));

const {executeDrop} = await import('./executeDrop');
const {useBasketStore} = await import('@/features/baskets/basketStore');
const {useCollectionStore} =
    await import('@/features/collections/collectionStore');

const t = (key: string) => key;
const asset = (id: string, workspaceId = 'ws1') =>
    ({id, name: id, workspace: {id: workspaceId}}) as unknown as Asset;
const col = (id: string, workspaceId = 'ws1', parentId?: string) =>
    ({
        id,
        name: id,
        workspaceId,
        parentId,
        capabilities: {},
    }) as unknown as CollectionNode;

const payload = (...assets: Asset[]) => ({
    type: 'assets' as const,
    assets,
    source: {scope: 's'},
});

function deps(confirmAnswer = true) {
    const queryClient = new QueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const reloadResults = vi.fn(() => Promise.resolve());
    const confirm = vi.fn(() => Promise.resolve(confirmAnswer));

    return {
        deps: {t, queryClient, reloadResults, confirm},
        invalidate,
        reloadResults,
        confirm,
    };
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('executeDrop assets', () => {
    it('adds to a collection and reloads the results', async () => {
        const {deps: d, reloadResults} = deps();
        const ok = await executeDrop(
            payload(asset('a'), asset('b')),
            {type: 'collection', collection: col('c')},
            'add',
            d
        );
        expect(ok).toBe(true);
        expect(api.addAssetsToCollection).toHaveBeenCalledWith(
            ['a', 'b'],
            '/collections/c'
        );
        expect(reloadResults).toHaveBeenCalled();
        expect(toast.success).toHaveBeenCalledWith('collection.assets_added');
    });

    it('moves and duplicates into a collection', async () => {
        const {deps: d} = deps();
        await executeDrop(
            payload(asset('a')),
            {type: 'collection', collection: col('c')},
            'move',
            d
        );
        expect(api.moveAssets).toHaveBeenCalledWith(['a'], '/collections/c');

        await executeDrop(
            payload(asset('a')),
            {type: 'collection', collection: col('c')},
            'copy',
            d
        );
        expect(api.copyAssets).toHaveBeenCalledWith(
            ['a'],
            '/collections/c',
            false,
            {
                withAttributes: true,
                withTags: true,
            }
        );
        // Same workspace: no confirmation
        expect(d.confirm).not.toHaveBeenCalled();
    });

    it('confirms a duplication into another workspace', async () => {
        const refused = deps(false);
        expect(
            await executeDrop(
                payload(asset('a', 'ws1')),
                {
                    type: 'workspace',
                    workspace: {id: 'ws2', name: 'W2'} as Workspace,
                },
                'copy',
                refused.deps
            )
        ).toBe(false);
        expect(api.copyAssets).not.toHaveBeenCalled();

        const accepted = deps(true);
        expect(
            await executeDrop(
                payload(asset('a', 'ws1')),
                {
                    type: 'workspace',
                    workspace: {id: 'ws2', name: 'W2'} as Workspace,
                },
                'copy',
                accepted.deps
            )
        ).toBe(true);
        expect(api.copyAssets).toHaveBeenCalledWith(
            ['a'],
            '/workspaces/ws2',
            false,
            {
                withAttributes: true,
                withTags: true,
            }
        );
    });

    it('adds to a story through the story asset and refreshes its queries', async () => {
        const {deps: d, invalidate} = deps();
        const story = {
            id: 'st',
            name: 'Story',
            workspace: {id: 'ws1'},
            storyCollection: {id: 'sc'},
        } as unknown as Asset;
        await executeDrop(
            payload(asset('a')),
            {type: 'story', story},
            'add',
            d
        );
        expect(api.addAssetsToCollection).toHaveBeenCalledWith(
            ['a'],
            '/assets/st'
        );
        expect(invalidate).toHaveBeenCalledWith({
            queryKey: ['story-assets', 'st'],
        });
        expect(toast.success).toHaveBeenCalledWith('story.assets_added');

        await executeDrop(
            payload(asset('a')),
            {type: 'story', story},
            'move',
            d
        );
        expect(api.moveAssets).toHaveBeenCalledWith(['a'], '/collections/sc');
    });

    it('adds to a basket and updates the store', async () => {
        const {deps: d, invalidate} = deps();
        const before = {
            id: 'b',
            name: 'B',
            assetCount: 1,
            capabilities: {edit: true},
        } as unknown as Basket;
        useBasketStore.setState({baskets: [before]});
        api.addToBasket.mockResolvedValueOnce({...before, assetCount: 3});

        await executeDrop(
            payload(asset('a'), asset('c')),
            {type: 'basket', basket: before},
            'add',
            d
        );
        expect(api.addToBasket).toHaveBeenCalledWith('b', ['a', 'c']);
        expect(useBasketStore.getState().baskets[0].assetCount).toBe(3);
        expect(invalidate).toHaveBeenCalledWith({
            queryKey: ['basket-assets', 'b'],
        });
        expect(toast.success).toHaveBeenCalledWith('basket.added');
    });

    it('reports a failure and does nothing else', async () => {
        const {deps: d, reloadResults} = deps();
        api.addAssetsToCollection.mockRejectedValueOnce(new Error('boom'));
        const ok = await executeDrop(
            payload(asset('a')),
            {type: 'collection', collection: col('c')},
            'add',
            d
        );
        expect(ok).toBe(false);
        expect(toast.error).toHaveBeenCalledWith('boom');
        expect(reloadResults).not.toHaveBeenCalled();
    });
});

describe('executeDrop collection', () => {
    beforeEach(() => {
        useCollectionStore.setState({
            collections: {
                root: col('root'),
                child: col('child', 'ws1', 'root'),
                other: col('other'),
            },
            pagers: {
                'ws:ws1': {
                    ids: ['root', 'other'],
                    loading: false,
                    loaded: true,
                },
                'root': {ids: ['child'], loading: false, loaded: true},
            },
        });
    });

    it('moves into a collection, then in the store', async () => {
        const {deps: d} = deps();
        const ok = await executeDrop(
            {type: 'collection', collection: col('child', 'ws1', 'root')},
            {type: 'collection', collection: col('other')},
            'move-collection',
            d
        );
        expect(ok).toBe(true);
        expect(api.moveCollection).toHaveBeenCalledWith('child', 'other');
        const s = useCollectionStore.getState();
        expect(s.collections.child.parentId).toBe('other');
        expect(s.pagers.root.ids).toEqual([]);
        expect(s.pagers.other.ids).toEqual(['child']);
        expect(toast.success).toHaveBeenCalledWith('collection.move.done');
    });

    it('moves to the workspace root', async () => {
        const {deps: d} = deps();
        await executeDrop(
            {type: 'collection', collection: col('child', 'ws1', 'root')},
            {type: 'workspace', workspace: {id: 'ws1', name: 'W'} as Workspace},
            'move-collection',
            d
        );
        expect(api.moveCollection).toHaveBeenCalledWith('child', undefined);
        expect(
            useCollectionStore.getState().collections.child.parentId
        ).toBeUndefined();
        expect(useCollectionStore.getState().pagers['ws:ws1'].ids).toContain(
            'child'
        );
    });

    it('leaves the store alone when the API refuses', async () => {
        const {deps: d} = deps();
        api.moveCollection.mockRejectedValueOnce(new Error('403'));
        const ok = await executeDrop(
            {type: 'collection', collection: col('child', 'ws1', 'root')},
            {type: 'collection', collection: col('other')},
            'move-collection',
            d
        );
        expect(ok).toBe(false);
        expect(useCollectionStore.getState().collections.child.parentId).toBe(
            'root'
        );
        expect(toast.error).toHaveBeenCalledWith('403');
    });
});
