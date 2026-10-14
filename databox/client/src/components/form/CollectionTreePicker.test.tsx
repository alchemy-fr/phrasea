import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Collection, Workspace} from '@/types/api';
import {
    pagerKey,
    useCollectionStore,
    type CollectionNode,
} from '@/features/collections/collectionStore';
import {CollectionTreePicker, type TreeSelection} from './CollectionTreePicker';

const capabilities = {createAsset: true, createCollection: true};

function workspace(id: string): Workspace {
    return {id, name: `WS ${id}`, capabilities} as unknown as Workspace;
}

function node(id: string, parentId?: string): CollectionNode {
    return {
        id,
        name: `Col ${id}`,
        workspaceId: 'w1',
        parentId,
        capabilities,
    } as unknown as CollectionNode;
}

function pager(ids: string[]) {
    return {ids, loading: false, loaded: true};
}

const selection: TreeSelection = {
    iri: '/collections/c2',
    workspaceId: 'w1',
    collectionId: 'c2',
    label: '',
};

function renderPicker() {
    act(() => {
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <CollectionTreePicker
                    value={selection}
                    onChange={() => undefined}
                    showSelection
                />
            </I18nextProvider>
        );
    });
}

describe('CollectionTreePicker', () => {
    beforeEach(() => {
        useCollectionStore.setState({
            workspaces: [workspace('w1'), workspace('w2')],
            workspacesLoaded: true,
            collections: {},
            pagers: {},
        });
    });

    it('reveals the selected collection and shows its path', () => {
        useCollectionStore.setState({
            collections: {c1: node('c1'), c2: node('c2', 'c1')},
            pagers: {
                [pagerKey('w1')]: pager(['c1']),
                [pagerKey('w1', 'c1')]: pager(['c2']),
            },
        });
        renderPicker();

        expect(
            screen
                .getAllByTestId('tree-picker-collection')
                .map(e => e.textContent)
        ).toEqual(['Col c1', 'Col c2']);
        expect(screen.getByTestId('tree-picker-selection').textContent).toBe(
            'WS w1Col c1Col c2'
        );
    });

    it('loads the ascendants of a selected collection missing from the store', async () => {
        const loadAscendants = vi.fn(async () => {
            useCollectionStore.setState({
                collections: {c1: node('c1'), c2: node('c2', 'c1')},
            });

            return {} as Collection;
        });
        useCollectionStore.setState({
            loadAscendants,
            pagers: {
                [pagerKey('w1')]: pager(['c1']),
                [pagerKey('w1', 'c1')]: pager(['c2']),
            },
        });
        renderPicker();

        expect(loadAscendants).toHaveBeenCalledWith('c2');
        expect(
            (await screen.findAllByTestId('tree-picker-collection')).map(
                e => e.textContent
            )
        ).toEqual(['Col c1', 'Col c2']);
    });
});
