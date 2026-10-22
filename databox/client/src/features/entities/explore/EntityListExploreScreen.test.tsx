import {beforeAll, beforeEach, describe, expect, it, vi} from 'vitest';
import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import type {AttributeEntity} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {ModalProvider} from '@/components/modals/ModalProvider';
import {EntityListExploreScreen} from './EntityListExploreScreen';

vi.mock('next/navigation', () => ({
    usePathname: () => '/entity-lists/list-1/explore',
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

vi.mock('next/link', () => ({
    default: ({href, children, ...props}: any) => (
        <a href={typeof href === 'string' ? href : '#'} {...props}>
            {children}
        </a>
    ),
}));

vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({
        status: 'authenticated',
        isAuthenticated: true,
        hasRole: () => false,
        login: vi.fn(),
        logout: vi.fn(),
    }),
    AppRole: {Admin: 'admin', DataboxAdmin: 'databox-admin', Tech: 'tech'},
}));

// The entity picker of the merge, as a plain select
vi.mock('@/components/form/selects', () => ({
    AttributeEntitySelect: ({onChange}: {onChange: (v: string) => void}) => (
        <select
            data-testid="merge-target"
            defaultValue=""
            onChange={e => onChange(e.target.value)}
        >
            <option value="" />
            <option value="bob">Bob</option>
        </select>
    ),
}));

const {api} = vi.hoisted(() => ({
    api: {
        deleteAttributeEntity: vi.fn(),
        getEntityList: vi.fn(),
        getAttributeEntities: vi.fn(),
        getAttributesOfDefinition: vi.fn(),
        getAttributeDefinitionsPage: vi.fn(),
        mergeAttributeEntities: vi.fn(),
        putAttributeEntity: vi.fn(),
        searchAssets: vi.fn(),
        getWorkspace: vi.fn(),
    },
}));

vi.mock('@/lib/api/metadata', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/metadata')>();

    return {
        ...actual,
        deleteAttributeEntity: api.deleteAttributeEntity,
        getEntityList: api.getEntityList,
        getAttributeEntities: api.getAttributeEntities,
        getAttributesOfDefinition: api.getAttributesOfDefinition,
        getAttributeDefinitionsPage: api.getAttributeDefinitionsPage,
        mergeAttributeEntities: api.mergeAttributeEntities,
        putAttributeEntity: api.putAttributeEntity,
    };
});
vi.mock('@/lib/api/assets', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/assets')>();

    return {...actual, searchAssets: api.searchAssets};
});
vi.mock('@/lib/api/collections', async importOriginal => {
    const actual =
        await importOriginal<typeof import('@/lib/api/collections')>();

    return {...actual, getWorkspace: api.getWorkspace};
});

beforeAll(() => {
    // Every observed element is in view
    globalThis.IntersectionObserver = class {
        constructor(private cb: IntersectionObserverCallback) {}
        observe(target: Element) {
            this.cb(
                [{isIntersecting: true, target} as IntersectionObserverEntry],
                this as unknown as IntersectionObserver
            );
        }
        disconnect() {}
        unobserve() {}
    } as unknown as typeof IntersectionObserver;
});

function value(
    id: string,
    name: string,
    options: Partial<AttributeEntity> = {}
): AttributeEntity {
    return {
        id,
        value: name,
        status: AttributeEntityStatus.Approved,
        translations: {},
        usage: {attributes: 8, humanAttributes: 1},
        ...options,
    } as AttributeEntity;
}

function asset(i: number) {
    return {
        id: `asset-${i}`,
        name: `Photo ${i}`,
        capabilities: {edit: true},
    };
}

const alice = value('alice', 'Alice');
const unknown = value('unknown-1', 'Unknown person · a1b2', {
    status: AttributeEntityStatus.Pending,
    usage: {attributes: 1, humanAttributes: 0},
});

const definition = {
    id: 'def-1',
    name: 'Persons',
    slug: 'persons',
    searchSlug: 'persons_entity_m',
    type: 'entity',
    enabled: true,
    entityList: '/entity-lists/list-1',
    workspace: '/workspaces/ws',
};

function renderScreen() {
    const i18n = createI18n('en');
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={i18n}>
                <TooltipPrimitive.Provider>
                    <ModalProvider>
                        <EntityListExploreScreen listId="list-1" />
                    </ModalProvider>
                </TooltipPrimitive.Provider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

/** Opens a Radix dropdown menu from its trigger (keyboard, jsdom has no pointer events) */
function openMenu(trigger: HTMLElement) {
    fireEvent.keyDown(trigger, {key: 'Enter'});

    return screen.findAllByRole('menuitem');
}

/** Opens the deletion confirmation of the value of a card */
async function openDelete(card: HTMLElement) {
    const items = await openMenu(
        within(card).getByTestId('entity-value-actions')
    );
    fireEvent.click(
        items.find(
            i => i.dataset.testid === 'entity-value-delete-button'
        ) as HTMLElement
    );

    return screen.findByTestId('form-dialog');
}

function submitButton(dialog: HTMLElement) {
    return dialog.querySelector('button[type="submit"]') as HTMLButtonElement;
}

describe('EntityListExploreScreen', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getEntityList.mockResolvedValue({
            id: 'list-1',
            name: 'Persons',
            workspace: '/workspaces/ws',
        });
        api.getWorkspace.mockResolvedValue({
            id: 'ws',
            name: 'ws',
            displayName: 'Workspace',
            capabilities: {edit: true},
        });
        api.getAttributeDefinitionsPage.mockResolvedValue({
            total: 1,
            items: [definition],
        });
        api.getAttributeEntities.mockImplementation(
            async (opts: {status?: number}) =>
                opts.status === AttributeEntityStatus.Pending
                    ? {total: 1, items: [unknown]}
                    : {total: 1, items: [alice]}
        );
        api.searchAssets.mockImplementation(async (opts: {ids?: string[]}) =>
            opts.ids
                ? {
                      total: opts.ids.length,
                      items: opts.ids.map(id => ({...asset(1), id})),
                  }
                : {
                      total: 8,
                      items: [1, 2, 3, 4, 5, 6].map(asset),
                      next: '/assets?page=2',
                  }
        );
        api.getAttributesOfDefinition.mockResolvedValue({
            total: 1,
            items: [{id: 'attr-1', asset: '/assets/asset-9'}],
        });
    });

    it('lists the approved values with a preview of their assets', async () => {
        renderScreen();

        const cards = await screen.findAllByTestId('entity-value-card');
        expect(cards).toHaveLength(1);
        expect(screen.getByTestId('entity-explore-title').textContent).toBe(
            'Persons'
        );
        expect(api.getAttributeEntities).toHaveBeenCalledWith(
            expect.objectContaining({
                list: 'list-1',
                status: AttributeEntityStatus.Approved,
                usage: true,
                limit: 24,
            })
        );
        expect(
            within(cards[0]).getByTestId('entity-value-references').textContent
        ).toBe('8 references');

        await waitFor(() =>
            expect(
                within(cards[0]).getAllByTestId('entity-value-thumb')
            ).toHaveLength(6)
        );
        expect(api.searchAssets).toHaveBeenCalledWith(
            expect.objectContaining({
                conditions: ['persons = "alice"'],
                workspaces: ['ws'],
                limit: 6,
            }),
            expect.anything()
        );
        expect(
            within(cards[0]).getByTestId('entity-value-more').textContent
        ).toBe('+2');
        const search = within(cards[0]).getByTestId('entity-value-open-search');
        expect(search.getAttribute('href')).toMatch(/^\/assets\?/);
        expect(screen.getByTestId('entity-explore-manage')).toBeTruthy();
    });

    it('sends the filter to the API', async () => {
        renderScreen();
        await screen.findAllByTestId('entity-value-card');

        fireEvent.change(screen.getByTestId('entity-explore-filter'), {
            target: {value: 'ali'},
        });
        await waitFor(() =>
            expect(api.getAttributeEntities).toHaveBeenLastCalledWith(
                expect.objectContaining({value: 'ali', list: 'list-1'})
            )
        );
    });

    it('lists the pending values, previewed through their attributes', async () => {
        // The total of the attributes counts their assets
        api.getAttributesOfDefinition.mockResolvedValue({
            total: 3,
            items: [{id: 'attr-1', asset: '/assets/asset-9'}],
            next: '/attributes?page=2',
        });
        renderScreen();
        await screen.findAllByTestId('entity-value-card');

        fireEvent.click(screen.getByTestId('entity-explore-status-pending'));
        await waitFor(() =>
            expect(api.getAttributeEntities).toHaveBeenLastCalledWith(
                expect.objectContaining({status: AttributeEntityStatus.Pending})
            )
        );
        await waitFor(() =>
            expect(
                screen
                    .getByTestId('entity-value-card')
                    .getAttribute('data-entity')
            ).toBe('unknown-1')
        );
        const card = screen.getByTestId('entity-value-card');
        expect(within(card).getByTestId('entity-value-pending')).toBeTruthy();
        await waitFor(() =>
            expect(
                within(card).getAllByTestId('entity-value-thumb')
            ).toHaveLength(1)
        );
        expect(api.getAttributesOfDefinition).toHaveBeenCalledWith(
            expect.objectContaining({
                definition: ['def-1'],
                value: 'unknown-1',
            }),
            expect.anything()
        );
        expect(within(card).getByTestId('entity-value-more').textContent).toBe(
            '+2'
        );
        // Not indexed: no search to open
        expect(
            within(card).queryByTestId('entity-value-open-search')
        ).toBeNull();
    });

    it('renames a value with its note', async () => {
        api.putAttributeEntity.mockResolvedValue(
            value('alice', 'Alice Smith', {note: 'the photographer'})
        );
        renderScreen();
        const [card] = await screen.findAllByTestId('entity-value-card');

        const items = await openMenu(
            within(card).getByTestId('entity-value-actions')
        );
        fireEvent.click(
            items.find(
                i => i.dataset.testid === 'entity-value-rename-button'
            ) as HTMLElement
        );
        fireEvent.change(
            await screen.findByTestId('entity-value-rename-value'),
            {
                target: {value: 'Alice Smith'},
            }
        );
        fireEvent.change(screen.getByTestId('entity-value-rename-note'), {
            target: {value: 'the photographer'},
        });
        fireEvent.submit(screen.getByTestId('entity-value-rename'));

        await waitFor(() =>
            expect(api.putAttributeEntity).toHaveBeenCalledWith('alice', {
                value: 'Alice Smith',
                note: 'the photographer',
            })
        );
    });

    it('merges a value into another one', async () => {
        api.mergeAttributeEntities.mockResolvedValue(value('bob', 'Bob'));
        renderScreen();
        const [card] = await screen.findAllByTestId('entity-value-card');

        const items = await openMenu(
            within(card).getByTestId('entity-value-actions')
        );
        fireEvent.click(
            items.find(
                i => i.dataset.testid === 'entity-value-merge-button'
            ) as HTMLElement
        );
        fireEvent.change(await screen.findByTestId('merge-target'), {
            target: {value: 'bob'},
        });

        await waitFor(() =>
            expect(api.mergeAttributeEntities).toHaveBeenCalledWith('bob', [
                'alice',
            ])
        );
    });

    it('deletes a value without reference after a plain confirmation', async () => {
        api.getAttributeEntities.mockResolvedValue({
            total: 1,
            items: [value('alice', 'Alice', {usage: undefined})],
        });
        api.deleteAttributeEntity.mockResolvedValue(undefined);
        renderScreen();
        const [card] = await screen.findAllByTestId('entity-value-card');
        const calls = api.getAttributeEntities.mock.calls.length;

        const dialog = await openDelete(card);
        expect(within(dialog).queryByRole('textbox')).toBeNull();
        expect(dialog.textContent).toContain('Delete "Alice"?');
        const confirm = submitButton(dialog);
        expect(confirm.disabled).toBe(false);
        fireEvent.click(confirm);

        await waitFor(() =>
            expect(api.deleteAttributeEntity).toHaveBeenCalledWith('alice')
        );
        await waitFor(() =>
            expect(api.getAttributeEntities.mock.calls.length).toBeGreaterThan(
                calls
            )
        );
    });

    it('asks to type a referenced value before deleting it', async () => {
        api.getAttributeEntities.mockResolvedValue({
            total: 1,
            items: [
                value('alice', 'Alice', {
                    usage: {attributes: 3, humanAttributes: 0},
                }),
            ],
        });
        api.deleteAttributeEntity.mockResolvedValue(undefined);
        renderScreen();
        const [card] = await screen.findAllByTestId('entity-value-card');

        const dialog = await openDelete(card);
        expect(dialog.textContent).toContain(
            'Its 3 references will be removed from the assets.'
        );
        const confirm = submitButton(dialog);
        expect(confirm.disabled).toBe(true);
        const input = within(dialog).getByRole('textbox');
        fireEvent.change(input, {target: {value: 'Ali'}});
        expect(confirm.disabled).toBe(true);
        fireEvent.change(input, {target: {value: 'Alice'}});
        expect(confirm.disabled).toBe(false);
        fireEvent.click(confirm);

        await waitFor(() =>
            expect(api.deleteAttributeEntity).toHaveBeenCalledWith('alice')
        );
    });

    it('does not delete the value when the confirmation is cancelled', async () => {
        renderScreen();
        const [card] = await screen.findAllByTestId('entity-value-card');

        const dialog = await openDelete(card);
        fireEvent.click(within(dialog).getByRole('button', {name: 'Cancel'}));

        await waitFor(() =>
            expect(screen.queryByTestId('form-dialog')).toBeNull()
        );
        expect(api.deleteAttributeEntity).not.toHaveBeenCalled();
    });

    it('hides the edition from the users who cannot edit the workspace', async () => {
        api.getWorkspace.mockResolvedValue({
            id: 'ws',
            name: 'ws',
            displayName: 'Workspace',
            capabilities: {edit: false},
        });
        renderScreen();
        const [card] = await screen.findAllByTestId('entity-value-card');
        await waitFor(() => expect(api.getWorkspace).toHaveBeenCalled());
        await screen.findByText('Workspace');

        expect(within(card).queryByTestId('entity-value-actions')).toBeNull();
        expect(screen.queryByTestId('entity-explore-manage')).toBeNull();
    });
});
