import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {PropsWithChildren, ReactElement} from 'react';
import {fireEvent, render, screen} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import type {AttributeEntity, EntityList} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import {EntityTree} from './EntityTree';

const api = vi.hoisted(() => ({getAttributeEntities: vi.fn()}));
vi.mock('@/lib/api/metadata', () => ({
    getAttributeEntities: api.getAttributeEntities,
    moveAttributeEntity: vi.fn(),
    postAttributeEntity: vi.fn(),
}));
vi.mock('@/components/chips', () => ({
    EntityChip: ({entity}: {entity: AttributeEntity}) => (
        <span>{entity.value}</span>
    ),
}));
vi.mock('@/components/form/selects', () => ({
    AttributeEntitySelect: () => null,
}));

const entity = (id: string) =>
    ({
        id,
        value: id,
        translations: {},
        createdAt: '',
        updatedAt: '',
        status: AttributeEntityStatus.Approved,
        hierarchyDepth: 0,
        childCount: 0,
    }) as unknown as AttributeEntity;

const list = {id: 'l1', name: 'Things', isHierarchy: true} as EntityList;

// Typed against the app's @types/react: the workspace holds a second copy,
// the one @tanstack/react-query resolves
const Provider = QueryClientProvider as unknown as (
    props: PropsWithChildren<{client: QueryClient}>
) => ReactElement;

function renderTree(selectedId: string | null) {
    const onSelect = vi.fn();
    const client = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });
    render(
        <Provider client={client}>
            <ModalProvider>
                <EntityTree
                    list={list}
                    selectedId={selectedId}
                    onSelect={onSelect}
                />
            </ModalProvider>
        </Provider>
    );

    return onSelect;
}

describe('EntityTree', () => {
    beforeEach(() => {
        api.getAttributeEntities.mockReset();
        api.getAttributeEntities.mockResolvedValue({
            total: 2,
            items: [entity('animal'), entity('object')],
        });
    });

    it('selects a node on click, and the top level with the root', async () => {
        const onSelect = renderTree('object');
        const nodes = await screen.findAllByTestId('entity-tree-node');
        const root = screen.getByTestId('entity-tree-root');

        expect(root.textContent).toBe('Things');
        expect(root.getAttribute('aria-selected')).toBe('false');
        expect(nodes.map(n => n.getAttribute('aria-selected'))).toEqual([
            'false',
            'true',
        ]);

        fireEvent.click(screen.getByText('animal'));
        expect(onSelect).toHaveBeenLastCalledWith(
            expect.objectContaining({id: 'animal'})
        );
        fireEvent.click(root);
        expect(onSelect).toHaveBeenLastCalledWith(null);
    });

    it('does not select a node through its buttons', async () => {
        const onSelect = renderTree(null);
        const [node] = await screen.findAllByTestId('entity-tree-node');

        fireEvent.click(node.querySelector('button')!);
        expect(onSelect).not.toHaveBeenCalled();
        expect(
            screen.getByTestId('entity-tree-root').getAttribute('aria-selected')
        ).toBe('true');
    });
});
