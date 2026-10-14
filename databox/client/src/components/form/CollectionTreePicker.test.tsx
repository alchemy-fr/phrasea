import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import type {ComponentProps} from 'react';
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

function renderPicker(
    props: Partial<ComponentProps<typeof CollectionTreePicker>> = {}
) {
    act(() => {
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <CollectionTreePicker
                    value={selection}
                    onChange={() => undefined}
                    showSelection
                    {...props}
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

    describe('a collection already loaded without children', () => {
        beforeEach(() => {
            useCollectionStore.setState({
                workspaces: [workspace('w1')],
                collections: {c1: node('c1')},
                pagers: {
                    [pagerKey('w1')]: pager(['c1']),
                    [pagerKey('w1', 'c1')]: pager([]),
                },
            });
        });

        it('can be expanded to create a sub-collection when allowCreate is set', () => {
            renderPicker({value: undefined, allowCreate: true});

            const toggle = screen.getByTestId('tree-picker-toggle');
            expect(toggle.className).not.toContain('invisible');
            // Only the workspace-level entry is visible before expanding
            expect(screen.getAllByText('New collection')).toHaveLength(1);

            act(() => {
                fireEvent.click(toggle);
            });

            expect(screen.getAllByText('New collection')).toHaveLength(2);
        });

        it('hides its toggle when creation is not allowed', () => {
            renderPicker({value: undefined});

            expect(
                screen.getByTestId('tree-picker-toggle').className
            ).toContain('invisible');
        });
    });
});
