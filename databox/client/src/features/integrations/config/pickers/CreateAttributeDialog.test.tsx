import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import {CreateAttributeDialog} from './CreateAttributeDialog';

const {api, toast, store} = vi.hoisted(() => ({
    api: {
        getAttributePolicies: vi.fn(),
        getAttributeFieldTypes: vi.fn(),
        getEntityLists: vi.fn(),
        postEntityList: vi.fn(),
        postAttributeDefinition: vi.fn(),
        deleteEntityList: vi.fn(),
    },
    toast: {success: vi.fn(), error: vi.fn()},
    store: {loadWorkspace: vi.fn()},
}));

vi.mock('sonner', () => ({toast}));
vi.mock('@/lib/api/metadata', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/metadata')>();

    return {...actual, ...api};
});
vi.mock('@/features/attributes/definitionsStore', () => ({
    useDefinitionsStore: {getState: () => store},
}));

// Used by the Radix checkbox, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

const policies = [
    {'@id': '/attribute-policies/p1', 'id': 'p1', 'name': 'Private'},
    {'@id': '/attribute-policies/p2', 'id': 'p2', 'name': 'Public'},
];
const fieldTypes = [
    {name: 'text', displayName: 'Text'},
    {name: 'entity', displayName: 'Entity'},
];

function renderDialog(
    props: Partial<Parameters<typeof CreateAttributeDialog>[0]> = {}
) {
    const resolve = vi.fn();
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <QueryClientProvider
                client={
                    new QueryClient({
                        defaultOptions: {queries: {retry: false}},
                    })
                }
            >
                <CreateAttributeDialog
                    open
                    onOpenChange={() => undefined}
                    resolve={resolve}
                    workspaceId="ws"
                    {...props}
                />
            </QueryClientProvider>
        </I18nextProvider>
    );

    return {resolve};
}

describe('CreateAttributeDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getAttributePolicies.mockResolvedValue({items: policies});
        api.getAttributeFieldTypes.mockResolvedValue(fieldTypes);
        api.getEntityLists.mockResolvedValue({items: []});
        api.deleteEntityList.mockResolvedValue(undefined);
        api.postEntityList.mockResolvedValue({
            '@id': '/entity-lists/l1',
            'id': 'l1',
            'name': 'Persons',
        });
        api.postAttributeDefinition.mockImplementation(async d => ({
            ...d,
            id: 'd1',
            slug: 'faces',
        }));
    });

    it('creates the list, then the entity attribute bound to it', async () => {
        const {resolve} = renderDialog({
            constraints: {type: 'entity', multiple: true, entityList: true},
        });

        // Faces by default, Persons for the list to create
        expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe(
            'Faces'
        );
        await waitFor(() =>
            expect(
                (screen.getByLabelText('List name') as HTMLInputElement).value
            ).toBe('Persons')
        );
        await waitFor(() =>
            expect(api.getAttributePolicies).toHaveBeenCalled()
        );

        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(resolve).toHaveBeenCalled());
        expect(api.postEntityList).toHaveBeenCalledWith('ws', {
            name: 'Persons',
        });
        expect(api.postAttributeDefinition).toHaveBeenCalledWith(
            expect.objectContaining({
                workspace: '/workspaces/ws',
                name: 'Faces',
                type: 'entity',
                multiple: true,
                entityList: '/entity-lists/l1',
                policy: '/attribute-policies/p2',
                searchable: true,
                editable: true,
            })
        );
        expect(resolve.mock.lastCall?.[0].slug).toBe('faces');
        expect(store.loadWorkspace).toHaveBeenCalledWith('ws');
        expect(api.deleteEntityList).not.toHaveBeenCalled();
    });

    it('removes the list it created when the attribute is refused', async () => {
        api.postAttributeDefinition.mockRejectedValue(new Error('Nope'));
        const {resolve} = renderDialog({
            constraints: {type: 'entity', multiple: true, entityList: true},
        });
        await waitFor(() => screen.getByLabelText('List name'));

        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Nope'));
        expect(api.deleteEntityList).toHaveBeenCalledWith('l1');
        expect(resolve).not.toHaveBeenCalled();
    });

    it('locks what the integration imposes', async () => {
        renderDialog({
            constraints: {type: 'entity', multiple: true},
            initialName: 'People',
        });

        expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe(
            'People'
        );
        const multiple = screen.getByTestId('create-attribute-multiple');
        expect(multiple.getAttribute('aria-checked')).toBe('true');
        expect(multiple.hasAttribute('disabled')).toBe(true);
        await waitFor(() =>
            expect(
                (screen.getByLabelText('Type') as HTMLButtonElement).disabled
            ).toBe(true)
        );
    });

    it('does not need a list for another type', async () => {
        const {resolve} = renderDialog({initialName: 'Caption'});
        await waitFor(() =>
            expect(api.getAttributePolicies).toHaveBeenCalled()
        );

        expect(screen.queryByLabelText('Entity list')).toBeNull();
        fireEvent.click(screen.getByRole('button', {name: 'Create'}));

        await waitFor(() => expect(resolve).toHaveBeenCalled());
        expect(api.postEntityList).not.toHaveBeenCalled();
        expect(api.postAttributeDefinition).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'Caption',
                type: 'text',
                multiple: false,
                entityList: null,
            })
        );
    });
});
