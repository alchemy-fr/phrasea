import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {Collection, Workspace} from '@/types/api';
import {pagerKey, useCollectionStore} from './collectionStore';

const {api} = vi.hoisted(() => ({api: {getWorkspaces: vi.fn()}}));

vi.mock('@/lib/api/collections', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/collections')>()),
    getWorkspaces: api.getWorkspaces,
}));

function workspace(id: string, name = id): Workspace {
    return {id, name, capabilities: {}} as unknown as Workspace;
}

function collection(id: string, parentId?: string): Collection {
    return {
        id,
        '@id': `/collections/${id}`,
        'name': id,
        'displayName': id,
        parentId,
        'capabilities': {createAsset: true, createCollection: true},
    } as unknown as Collection;
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
