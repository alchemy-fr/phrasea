import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import type {Attribute, AttributeEntity} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {FacesScreen} from './FacesScreen';

vi.mock('next/navigation', () => ({
    usePathname: () => '/faces',
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

vi.mock('@/features/collections/collectionStore', () => {
    const state = {
        workspaces: [{id: 'ws', name: 'Workspace', displayName: 'Workspace'}],
        loadWorkspaces: vi.fn(),
    };

    return {
        useCollectionStore: (selector: (s: typeof state) => any) =>
            selector(state),
    };
});

const {api} = vi.hoisted(() => ({
    api: {
        getIntegrationsOfContext: vi.fn(),
        getAttributeEntities: vi.fn(),
        getAttributeEntity: vi.fn(),
        getAttributesOfDefinition: vi.fn(),
        mergeAttributeEntities: vi.fn(),
        putAttributeEntity: vi.fn(),
        postAttributeEntity: vi.fn(),
        searchAssets: vi.fn(),
        patchAssetAttribute: vi.fn(),
        deleteAssetAttribute: vi.fn(),
    },
}));

vi.mock('@/lib/api/integrations', async importOriginal => {
    const actual =
        await importOriginal<typeof import('@/lib/api/integrations')>();

    return {...actual, getIntegrationsOfContext: api.getIntegrationsOfContext};
});
vi.mock('@/lib/api/metadata', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/metadata')>();

    return {
        ...actual,
        getAttributeEntities: api.getAttributeEntities,
        getAttributeEntity: api.getAttributeEntity,
        getAttributesOfDefinition: api.getAttributesOfDefinition,
        mergeAttributeEntities: api.mergeAttributeEntities,
        putAttributeEntity: api.putAttributeEntity,
        postAttributeEntity: api.postAttributeEntity,
    };
});
vi.mock('@/lib/api/assets', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/assets')>();

    return {
        ...actual,
        searchAssets: api.searchAssets,
        patchAssetAttribute: api.patchAssetAttribute,
        deleteAssetAttribute: api.deleteAssetAttribute,
    };
});

const integration = {
    id: 'int-1',
    integration: 'core.face_recognition',
    enabled: true,
    workspace: {id: 'ws'},
    capabilities: {use: true, interact: true},
    config: {
        entityList: 'list-1',
        attribute: 'persons',
        attributeDefinition: 'def-1',
    },
};

function person(
    id: string,
    value: string,
    options: Partial<AttributeEntity> = {}
): AttributeEntity {
    return {
        id,
        value,
        status: AttributeEntityStatus.Approved,
        translations: {},
        usage: {attributes: 2, humanAttributes: 1},
        ...options,
    } as AttributeEntity;
}

function face(
    i: number,
    entity: AttributeEntity | undefined,
    origin: 'human' | 'machine' = 'machine'
): Attribute {
    return {
        id: `attr-${i}`,
        asset: `/assets/asset-${i}`,
        definition: {id: 'def-1', slug: 'persons'},
        value: entity
            ? {
                  id: entity.id,
                  value:
                      entity.status === AttributeEntityStatus.Pending
                          ? null
                          : entity.value,
                  status: entity.status,
              }
            : null,
        origin,
        confidence: origin === 'human' ? 1 : 0.8,
        assetAnnotations: [{type: 'rect', x: 0.1, y: 0.1, w: 0.2, h: 0.3}],
    } as unknown as Attribute;
}

function asset(i: number) {
    return {
        id: `asset-${i}`,
        name: `Photo ${i}`,
        preview: {file: {url: `https://cdn.test/p${i}.jpg`}},
        capabilities: {edit: true},
    };
}

const alice = person('alice', 'Alice');
const unknown = person('unknown-1', 'Unknown person · a1b2', {
    status: AttributeEntityStatus.Pending,
    usage: {attributes: 1, humanAttributes: 0},
});

function renderScreen() {
    const i18n = createI18n('en');
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={i18n}>
                <TooltipPrimitive.Provider>
                    <FacesScreen />
                </TooltipPrimitive.Provider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

describe('FacesScreen', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getIntegrationsOfContext.mockResolvedValue({
            total: 1,
            items: [integration],
        });
        api.getAttributeEntities.mockImplementation(
            async (opts: {status?: number}) =>
                opts.status === AttributeEntityStatus.Pending
                    ? {total: 1, items: [unknown]}
                    : {total: 1, items: [alice]}
        );
        api.getAttributesOfDefinition.mockResolvedValue({
            total: 3,
            items: [
                face(1, unknown),
                face(2, alice, 'human'),
                face(3, alice, 'machine'),
            ],
        });
        api.searchAssets.mockResolvedValue({
            total: 3,
            items: [asset(1), asset(2), asset(3)],
        });
    });

    it('lists the faces and the named persons', async () => {
        renderScreen();

        expect(await screen.findAllByTestId('face-card')).toHaveLength(3);
        expect(screen.getByTestId('faces-count').textContent).toBe('3 faces');
        expect(screen.getByTestId('faces-person').textContent).toBe('Alice2');
        expect(screen.getByTestId('faces-person-unnamed').textContent).toBe(
            'Unnamed1'
        );
        expect(screen.getByText('Recognized · 80%')).toBeTruthy();
        expect(api.getAttributesOfDefinition).toHaveBeenCalledWith(
            expect.objectContaining({definition: 'def-1', value: undefined}),
            expect.anything()
        );
        expect(api.searchAssets).toHaveBeenCalledWith(
            expect.objectContaining({ids: ['asset-1', 'asset-2', 'asset-3']}),
            expect.anything()
        );
    });

    it('filters the faces of a person and shows its header', async () => {
        renderScreen();
        await screen.findAllByTestId('face-card');

        fireEvent.click(screen.getByTestId('faces-person'));
        await waitFor(() =>
            expect(api.getAttributesOfDefinition).toHaveBeenLastCalledWith(
                expect.objectContaining({value: ['alice']}),
                expect.anything()
            )
        );
        expect(await screen.findByTestId('person-header')).toBeTruthy();
        expect(screen.getByText('1 reference face')).toBeTruthy();
    });

    it('renames a person with a note', async () => {
        api.putAttributeEntity.mockResolvedValue(
            person('alice', 'Alice Smith', {note: 'the photographer'})
        );
        renderScreen();
        await screen.findAllByTestId('face-card');
        fireEvent.click(screen.getByTestId('faces-person'));
        fireEvent.click(await screen.findByTestId('person-rename-button'));

        fireEvent.change(screen.getByTestId('person-rename-value'), {
            target: {value: 'Alice Smith'},
        });
        fireEvent.change(screen.getByTestId('person-rename-note'), {
            target: {value: 'the photographer'},
        });
        fireEvent.submit(screen.getByTestId('person-rename'));

        await waitFor(() =>
            expect(api.putAttributeEntity).toHaveBeenCalledWith('alice', {
                value: 'Alice Smith',
                note: 'the photographer',
            })
        );
    });

    it('confirms a recognized face and forgets a face', async () => {
        api.patchAssetAttribute.mockResolvedValue(face(3, alice, 'human'));
        api.deleteAssetAttribute.mockResolvedValue(undefined);
        renderScreen();
        const cards = await screen.findAllByTestId('face-card');

        fireEvent.click(
            cards[2].querySelector('[data-testid=face-confirm]') as HTMLElement
        );
        await waitFor(() =>
            expect(api.patchAssetAttribute).toHaveBeenCalledWith('attr-3', {
                value: 'alice',
            })
        );

        fireEvent.click(
            cards[1].querySelector('[data-testid=face-remove]') as HTMLElement
        );
        await waitFor(() =>
            expect(api.deleteAssetAttribute).toHaveBeenCalledWith('attr-2')
        );
    });

    it('groups the unnamed faces by unknown person', async () => {
        api.getAttributesOfDefinition.mockImplementation(
            async (opts: {value?: string[]}) =>
                opts.value?.includes('unknown-1')
                    ? {total: 1, items: [face(1, unknown)]}
                    : {total: 3, items: []}
        );
        renderScreen();
        await screen.findByTestId('faces-person-unnamed');

        fireEvent.click(screen.getByTestId('faces-person-unnamed'));
        const groups = await screen.findAllByTestId('person-group');
        expect(groups).toHaveLength(1);
        expect(
            groups[0].querySelector('[data-testid=person-group-count]')
                ?.textContent
        ).toBe('1 face');
        await waitFor(() =>
            expect(
                groups[0].querySelectorAll('[data-testid=face-card]')
            ).toHaveLength(1)
        );
        expect(
            groups[0].querySelector('[data-testid=person-group-name]')
        ).toBeTruthy();
    });

    it('explains when no workspace recognizes faces', async () => {
        api.getIntegrationsOfContext.mockResolvedValue({total: 0, items: []});
        renderScreen();

        expect(await screen.findByTestId('faces-empty')).toBeTruthy();
    });
});
