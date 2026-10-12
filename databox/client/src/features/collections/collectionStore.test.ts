import {beforeEach, describe, expect, it} from 'vitest';
import type {Collection} from '@/types/api';
import {pagerKey, useCollectionStore} from './collectionStore';

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
