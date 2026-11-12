import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import {createI18n} from '@/i18n';
import {EntityListManageScreen} from './EntityListManageScreen';

vi.mock('next/navigation', () => ({
    usePathname: () => '/entity-lists/list-1/manage',
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

// The values grid has its own tests
vi.mock('@/features/workspaces/manage/entities/EntityGrid', () => ({
    EntityGrid: ({list}: {list: {name: string}}) => (
        <div data-testid="entity-grid">{list.name}</div>
    ),
}));

const {api} = vi.hoisted(() => ({
    api: {
        getEntityList: vi.fn(),
        putEntityList: vi.fn(),
        getWorkspace: vi.fn(),
    },
}));

vi.mock('@/lib/api/metadata', async importOriginal => {
    const actual = await importOriginal<typeof import('@/lib/api/metadata')>();

    return {
        ...actual,
        getEntityList: api.getEntityList,
        putEntityList: api.putEntityList,
    };
});
vi.mock('@/lib/api/collections', async importOriginal => {
    const actual =
        await importOriginal<typeof import('@/lib/api/collections')>();

    return {...actual, getWorkspace: api.getWorkspace};
});

function list(capabilities: {edit: boolean; editValues: boolean}) {
    return {
        id: 'list-1',
        name: 'Colors',
        workspace: '/workspaces/ws',
        updatedAt: '2026-11-11T00:00:00+00:00',
        capabilities: {delete: false, editPermissions: false, ...capabilities},
    };
}

function renderScreen() {
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });

    return render(
        <QueryClientProvider client={queryClient}>
            <I18nextProvider i18n={createI18n('en')}>
                <ModalProvider>
                    <EntityListManageScreen listId="list-1" />
                </ModalProvider>
            </I18nextProvider>
        </QueryClientProvider>
    );
}

describe('EntityListManageScreen', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getWorkspace.mockResolvedValue({
            id: 'ws',
            name: 'ws',
            enabledLocales: ['en'],
            capabilities: {edit: false},
        });
    });

    it('refuses the users who cannot manage the values', async () => {
        api.getEntityList.mockResolvedValue(
            list({edit: false, editValues: false})
        );
        renderScreen();

        const denied = await screen.findByTestId('entity-manage-denied');
        expect(denied.textContent).toContain(
            'You are not allowed to manage this list'
        );
        expect(denied.querySelector('a')?.getAttribute('href')).toBe(
            '/entity-lists/list-1/explore'
        );
        expect(screen.queryByTestId('entity-grid')).toBeNull();
        expect(api.getWorkspace).not.toHaveBeenCalled();
    });

    it('shows the values, without the properties, to the value managers', async () => {
        api.getEntityList.mockResolvedValue(
            list({edit: false, editValues: true})
        );
        renderScreen();

        expect((await screen.findByTestId('entity-grid')).textContent).toBe(
            'Colors'
        );
        expect(screen.queryByTestId('entity-manage-properties')).toBeNull();
    });

    it('lets the operators edit the list properties', async () => {
        api.getEntityList.mockResolvedValue(
            list({edit: true, editValues: true})
        );
        api.putEntityList.mockResolvedValue({
            ...list({edit: true, editValues: true}),
            name: 'Palette',
        });
        renderScreen();

        fireEvent.click(await screen.findByTestId('entity-manage-properties'));
        const name = await screen.findByDisplayValue('Colors');
        fireEvent.change(name, {target: {value: 'Palette'}});
        fireEvent.click(screen.getByRole('button', {name: /Save/}));

        await waitFor(() =>
            expect(api.putEntityList).toHaveBeenCalledWith(
                'list-1',
                expect.objectContaining({name: 'Palette'})
            )
        );
        await waitFor(() =>
            expect(screen.getByTestId('entity-grid').textContent).toBe(
                'Palette'
            )
        );
        expect(screen.queryByTestId('entity-list-permissions')).toBeNull();
    });
});
