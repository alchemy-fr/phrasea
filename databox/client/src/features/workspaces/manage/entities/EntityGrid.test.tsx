import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';
import type {PropsWithChildren, ReactElement} from 'react';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import type {AttributeEntity, EntityList, Workspace} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {createI18n} from '@/i18n';
import {EntityGrid} from './EntityGrid';

// jsdom has no layout: the virtualizer would render no row
vi.mock('@tanstack/react-virtual', () => ({
    useVirtualizer: ({
        count,
        estimateSize,
        getItemKey,
    }: {
        count: number;
        estimateSize: () => number;
        getItemKey: (i: number) => string | number;
    }) => ({
        getVirtualItems: () =>
            Array.from({length: count}, (_, index) => ({
                index,
                key: getItemKey(index),
                start: index * estimateSize(),
            })),
        getTotalSize: () => count * estimateSize(),
        measureElement: () => {},
        scrollToIndex: () => {},
    }),
}));

vi.mock('@alchemy/next-ui/hooks/useDebouncedValue', () => ({
    useDebouncedValue: <T,>(v: T) => v,
}));

const api = vi.hoisted(() => ({
    getAttributeEntities: vi.fn(),
    postAttributeEntity: vi.fn(),
}));
vi.mock('@/lib/api/metadata', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/api/metadata')>()),
    getAttributeEntities: api.getAttributeEntities,
    postAttributeEntity: api.postAttributeEntity,
}));

// The tree has its own tests: its props are what matters here
vi.mock('./hierarchy/EntityTree', () => ({
    hierarchyQueryKey: (listId: string) => ['entity-hierarchy', listId],
    EntityTree: ({
        selectedId,
        reveal,
        onAdd,
    }: {
        selectedId?: string | null;
        reveal?: {openIds: string[]} | null;
        onAdd?: (parent: unknown) => void;
    }) => (
        <div
            data-testid="entity-tree"
            data-selected={String(selectedId)}
            data-open={reveal?.openIds.join(',') ?? ''}
        >
            <button
                data-testid="tree-add"
                onClick={() =>
                    onAdd?.({
                        id: 'tours',
                        value: 'Tours',
                        path: [{id: 'indre', value: 'Indre-et-Loire'}],
                    })
                }
            />
        </div>
    ),
}));
vi.mock('@/features/entities/explore/ExploreEntityListButton', () => ({
    ExploreEntityListButton: () => null,
}));

beforeAll(() => {
    globalThis.IntersectionObserver = class {
        observe() {}
        disconnect() {}
        unobserve() {}
    } as unknown as typeof IntersectionObserver;
    globalThis.ResizeObserver = class {
        observe() {}
        disconnect() {}
        unobserve() {}
    } as unknown as typeof ResizeObserver;
    Element.prototype.scrollTo = () => {};
});

// Rue Victor Hugo, under a placeholder of Tours
const street = {
    id: 'rvh',
    value: 'Rue Victor Hugo',
    translations: {},
    createdAt: '',
    updatedAt: '',
    status: AttributeEntityStatus.Approved,
    parentId: 'none',
    ancestorIds: ['none', 'tours', 'indre'],
    path: [
        {id: 'indre', value: 'Indre-et-Loire'},
        {id: 'tours', value: 'Tours'},
    ],
} as unknown as AttributeEntity;

const list = {id: 'l1', name: 'Geo', isHierarchy: true} as EntityList;
const workspace = {id: 'w1', enabledLocales: []} as unknown as Workspace;

// Typed against the app's @types/react: the workspace holds a second copy,
// the one @tanstack/react-query resolves
const Provider = QueryClientProvider as unknown as (
    props: PropsWithChildren<{client: QueryClient}>
) => ReactElement;

function renderGrid() {
    const client = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });
    render(
        <Provider client={client}>
            <I18nextProvider i18n={createI18n('en')}>
                <ModalProvider>
                    <EntityGrid
                        list={list}
                        workspace={workspace}
                        onBack={() => {}}
                    />
                </ModalProvider>
            </I18nextProvider>
        </Provider>
    );
}

/** The last request of the table (not the pending count) */
const lastListing = () =>
    api.getAttributeEntities.mock.calls
        .map(c => c[0] as Record<string, unknown>)
        .filter(p => p.usage)
        .at(-1) ?? {};

describe('EntityGrid', () => {
    beforeEach(() => {
        api.getAttributeEntities.mockReset();
        api.getAttributeEntities.mockResolvedValue({
            total: 1,
            items: [street],
        });
    });

    it('searches every level without the tree, and goes back to the level of a result', async () => {
        renderGrid();
        const tree = screen.getByTestId('entity-tree');
        await waitFor(() => expect(lastListing().depth).toBe(0));
        expect(tree.parentElement!.className).not.toContain('hidden');
        expect(screen.queryByTestId('entity-reveal')).toBeNull();
        expect(screen.queryByTestId('cell-path')).toBeNull();

        fireEvent.change(screen.getByTestId('entity-search'), {
            target: {value: 'victor'},
        });
        await waitFor(() => expect(lastListing().value).toBe('victor'));
        expect(lastListing()).not.toHaveProperty('parent');
        expect(lastListing()).not.toHaveProperty('depth');
        expect(tree.parentElement!.className).toContain('hidden');
        expect(screen.getByTestId('entity-table-parent').textContent).toBe(
            'Search results, all levels'
        );

        expect((await screen.findByTestId('cell-path')).textContent).toBe(
            'Indre-et-Loire / Tours'
        );

        fireEvent.click(await screen.findByTestId('entity-reveal'));
        expect(
            (screen.getByTestId('entity-search') as HTMLInputElement).value
        ).toBe('');
        expect(tree.parentElement!.className).not.toContain('hidden');
        // The placeholder holding it is selected, its ancestors open
        expect(tree.dataset.selected).toBe('none');
        expect(tree.dataset.open).toBe('tours,indre');
        expect(screen.getByTestId('entity-table-parent').textContent).toBe(
            'Children of "Indre-et-Loire / Tours / empty level"'
        );
        await waitFor(() => expect(lastListing().parent).toBe('none'));
        expect(lastListing().value).toBeUndefined();
    });

    it('creates a value from the "+" of the tree, under its node', async () => {
        api.postAttributeEntity.mockResolvedValue({...street, id: 'new'});
        renderGrid();
        // No general "New value" button in a hierarchy
        expect(screen.queryByTestId('entity-create')).toBeNull();

        fireEvent.click(screen.getByTestId('tree-add'));
        const dialog = await screen.findByTestId('entity-form-dialog');
        expect(screen.getByTestId('entity-form-parent').textContent).toBe(
            'In: Indre-et-Loire / Tours'
        );
        fireEvent.change(dialog.querySelector('input')!, {
            target: {value: 'Saint-Cyr'},
        });
        fireEvent.click(screen.getByRole('button', {name: /Save/}));

        await waitFor(() =>
            expect(api.postAttributeEntity).toHaveBeenCalledWith(
                'l1',
                expect.objectContaining({
                    value: 'Saint-Cyr',
                    parent: '/attribute-entities/tours',
                })
            )
        );
    });
});
