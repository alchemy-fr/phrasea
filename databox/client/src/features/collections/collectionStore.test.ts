import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Collection, Workspace} from '@/types/api';
import {pagerKey, useCollectionStore} from './collectionStore';

const {api} = vi.hoisted(() => ({
    api: {getWorkspaces: vi.fn(), getCollections: vi.fn()},
}));

vi.mock('@/lib/api/collections', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/collections')>()),
    getWorkspaces: api.getWorkspaces,
    getCollections: api.getCollections,
}));

function workspace(id: string, name = id): Workspace {
    return {id, name, capabilities: {}} as unknown as Workspace;
}

function collection(
    id: string,
    parentId?: string,
    name = id,
    createdAt = '2026-01-01T00:00:00+00:00'
): Collection {
    return {
        id,
        '@id': `/collections/${id}`,
        name,
        'displayName': name,
        parentId,
        createdAt,
        'capabilities': {createAsset: true, createCollection: true},
    } as unknown as Collection;
}

function rootIds(): string[] {
    return useCollectionStore.getState().pagers[pagerKey('ws')].ids;
}

describe('useCollectionStore.upsertCollection', () => {
    beforeEach(() => {
        useCollectionStore.setState({collections: {}, pagers: {}});
    });

    it('knows that a created collection has no children', () => {
        useCollectionStore
            .getState()
            .upsertCollection(collection('c1'), 'ws', true);

        const {pagers} = useCollectionStore.getState();
        expect(pagers[pagerKey('ws')].ids).toEqual(['c1']);
        expect(pagers[pagerKey('ws', 'c1')]).toMatchObject({
            ids: [],
            loaded: true,
        });
    });

    it('lists a sub-collection created under a new collection', () => {
        const {upsertCollection} = useCollectionStore.getState();
        upsertCollection(collection('c1'), 'ws', true);
        upsertCollection(collection('c2', 'c1'), 'ws', true);

        expect(
            useCollectionStore.getState().pagers[pagerKey('ws', 'c1')].ids
        ).toEqual(['c2']);
    });

    it('does not assume anything about the children of a loaded collection', () => {
        useCollectionStore.getState().upsertCollection(collection('c1'), 'ws');

        expect(
            useCollectionStore.getState().pagers[pagerKey('ws', 'c1')]
        ).toBeUndefined();
    });
});

describe('useCollectionStore workspaces', () => {
    beforeEach(() => {
        api.getWorkspaces.mockReset();
        useCollectionStore.setState({
            workspaces: [],
            workspacesById: {},
            workspacesResolved: {},
            workspacesNext: undefined,
            workspacesTotal: undefined,
            workspacesLoaded: false,
            workspacesLoading: false,
        });
    });

    it('loads the workspaces page by page', async () => {
        api.getWorkspaces
            .mockResolvedValueOnce({
                items: [workspace('a'), workspace('b')],
                total: 3,
                next: '/workspaces?page=2',
            })
            .mockResolvedValueOnce({items: [workspace('c')], total: 3});
        const {loadWorkspaces, loadMoreWorkspaces} =
            useCollectionStore.getState();

        await loadWorkspaces();
        expect(api.getWorkspaces).toHaveBeenLastCalledWith({limit: 50});
        expect(useCollectionStore.getState().workspacesNext).toBe(
            '/workspaces?page=2'
        );

        await loadMoreWorkspaces();
        expect(api.getWorkspaces).toHaveBeenLastCalledWith({
            url: '/workspaces?page=2',
        });
        const state = useCollectionStore.getState();
        expect(state.workspaces.map(w => w.id)).toEqual(['a', 'b', 'c']);
        expect(state.workspacesNext).toBeUndefined();
        expect(Object.keys(state.workspacesById)).toEqual(['a', 'b', 'c']);
    });

    it('lists an unknown upserted workspace only once the list is complete', async () => {
        api.getWorkspaces.mockResolvedValueOnce({
            items: [workspace('a')],
            total: 2,
            next: '/workspaces?page=2',
        });
        const {loadWorkspaces, upsertWorkspace} = useCollectionStore.getState();
        await loadWorkspaces();

        upsertWorkspace(workspace('z'));
        upsertWorkspace(workspace('a', 'Renamed'));
        let state = useCollectionStore.getState();
        expect(state.workspaces.map(w => w.name)).toEqual(['Renamed']);
        expect(state.workspacesById.z).toBeTruthy();

        useCollectionStore.setState({workspacesNext: undefined});
        upsertWorkspace(workspace('y'));
        state = useCollectionStore.getState();
        expect(state.workspaces.map(w => w.id)).toEqual(['a', 'y']);
    });

    it('resolves the ids asked for in the same tick with one request', async () => {
        api.getWorkspaces.mockImplementation(
            async ({ids}: {ids: string[]}) => ({
                items: ids.filter(id => id !== 'gone').map(id => workspace(id)),
                total: ids.length,
            })
        );
        const {ensureWorkspaces} = useCollectionStore.getState();

        await Promise.all([
            ensureWorkspaces(['a', 'gone']),
            ensureWorkspaces(['a', 'b']),
        ]);

        expect(api.getWorkspaces).toHaveBeenCalledTimes(1);
        expect(api.getWorkspaces).toHaveBeenCalledWith({
            ids: ['a', 'gone', 'b'],
            limit: 100,
        });
        expect(
            Object.keys(useCollectionStore.getState().workspacesById)
        ).toEqual(['a', 'b']);
        // Neither the known ones nor the missing one are asked for again
        await ensureWorkspaces(['a', 'gone']);
        expect(api.getWorkspaces).toHaveBeenCalledTimes(1);
        // The resolved workspaces stay out of the paged list
        expect(useCollectionStore.getState().workspaces).toEqual([]);
    });
});

describe('useCollectionStore order', () => {
    beforeEach(() => {
        api.getCollections.mockReset();
        useCollectionStore.setState({collections: {}, pagers: {}});
    });

    it('inserts a created collection at its place', () => {
        const {upsertCollection} = useCollectionStore.getState();
        upsertCollection(collection('a', undefined, 'Lot 1'), 'ws', true);
        upsertCollection(collection('b', undefined, 'Zoo'), 'ws', true);
        upsertCollection(collection('c', undefined, 'Lot 10'), 'ws', true);
        upsertCollection(collection('d', undefined, 'lot 2'), 'ws', true);
        upsertCollection(collection('e', undefined, 'Été'), 'ws', true);

        expect(rootIds()).toEqual(['e', 'a', 'd', 'c', 'b']);
        expect(useCollectionStore.getState().pagers[pagerKey('ws')].total).toBe(
            5
        );
    });

    it('breaks ties by creation date, then id', () => {
        const {upsertCollection} = useCollectionStore.getState();
        upsertCollection(
            collection('b', undefined, 'Same', '2026-01-02T00:00:00+00:00'),
            'ws'
        );
        upsertCollection(
            collection('c', undefined, 'same', '2026-01-01T00:00:00+00:00'),
            'ws'
        );
        upsertCollection(
            collection('a', undefined, 'SAME', '2026-01-02T00:00:00+00:00'),
            'ws'
        );

        expect(rootIds()).toEqual(['c', 'a', 'b']);
    });

    it('moves a renamed collection to its new place', () => {
        const {upsertCollection} = useCollectionStore.getState();
        upsertCollection(collection('a', undefined, 'Alpha'), 'ws');
        upsertCollection(collection('b', undefined, 'Beta'), 'ws');
        upsertCollection(collection('c', undefined, 'Gamma'), 'ws');

        upsertCollection(collection('a', undefined, 'Zeta'), 'ws');
        expect(rootIds()).toEqual(['b', 'c', 'a']);

        // Same name: the place is kept
        useCollectionStore.setState(s => ({
            pagers: {
                ...s.pagers,
                [pagerKey('ws')]: {
                    ...s.pagers[pagerKey('ws')],
                    ids: ['a', 'b', 'c'],
                },
            },
        }));
        upsertCollection(collection('a', undefined, 'Zeta'), 'ws');
        expect(rootIds()).toEqual(['a', 'b', 'c']);
    });

    it('inserts a moved collection at its place in the target', () => {
        const {upsertCollection, moveCollection} =
            useCollectionStore.getState();
        upsertCollection(collection('p'), 'ws', true);
        upsertCollection(collection('x', 'p', 'Beta'), 'ws', true);
        upsertCollection(collection('y', 'p', 'Delta'), 'ws', true);
        upsertCollection(collection('m', undefined, 'Charlie'), 'ws', true);

        moveCollection('m', 'p');

        const {pagers, collections} = useCollectionStore.getState();
        expect(pagers[pagerKey('ws', 'p')].ids).toEqual(['x', 'm', 'y']);
        expect(rootIds()).toEqual(['p']);
        expect(collections.m.parentId).toBe('p');
    });

    it('keeps a local collection not indexed yet at its place on reload', async () => {
        const {upsertCollection, loadChildren} = useCollectionStore.getState();
        upsertCollection(collection('local', undefined, 'Lot 2'), 'ws', true);
        api.getCollections.mockResolvedValueOnce({
            items: [
                collection('a', undefined, 'Lot 1'),
                collection('b', undefined, 'Lot 10'),
            ],
            total: 3,
            next: '/collections?page=2',
        });

        await loadChildren('ws', undefined, true);

        expect(rootIds()).toEqual(['a', 'local', 'b']);
    });

    it('places the local collections of the loaded range on load more', async () => {
        const {loadMore} = useCollectionStore.getState();
        useCollectionStore.setState({
            collections: {
                a: {...collection('a', undefined, 'A'), workspaceId: 'ws'},
                b: {...collection('b', undefined, 'B'), workspaceId: 'ws'},
                local: {
                    ...collection('local', undefined, 'D'),
                    workspaceId: 'ws',
                },
            },
            pagers: {
                [pagerKey('ws')]: {
                    ids: ['a', 'b', 'local'],
                    next: '/collections?page=2',
                    total: 5,
                    loading: false,
                    loaded: true,
                },
            },
        });
        api.getCollections.mockResolvedValueOnce({
            items: [
                collection('c', undefined, 'C'),
                collection('e', undefined, 'E'),
            ],
            total: 5,
        });

        await loadMore('ws');

        expect(api.getCollections).toHaveBeenCalledWith({
            url: '/collections?page=2',
        });
        expect(rootIds()).toEqual(['a', 'b', 'c', 'local', 'e']);
        expect(
            useCollectionStore.getState().pagers[pagerKey('ws')].next
        ).toBeUndefined();
    });
});
