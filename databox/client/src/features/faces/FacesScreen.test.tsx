import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import {AssetFace, FaceIdentityOrigin} from '@/types/api';
import {FacesScreen} from './FacesScreen';

const auth = vi.hoisted(() => ({roles: ['edit_faces'] as string[]}));

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
        hasRole: (role: string) => auth.roles.includes(role),
        login: vi.fn(),
        logout: vi.fn(),
    }),
    AppRole: {
        Admin: 'admin',
        DataboxAdmin: 'databox-admin',
        Tech: 'tech',
        EditFaces: 'edit_faces',
    },
}));

vi.mock('@/features/collections/collectionStore', () => {
    const state = {
        workspaces: [
            {id: 'ws', name: 'Workspace', displayName: 'Workspace'},
            {id: 'ws2', name: 'Other', displayName: 'Other'},
        ],
        loadWorkspaces: vi.fn(),
    };

    return {
        useCollectionStore: (selector: (s: typeof state) => any) =>
            selector(state),
    };
});

const {api} = vi.hoisted(() => ({
    api: {
        getFaces: vi.fn(),
        getFaceIdentities: vi.fn(),
        identifyFace: vi.fn(),
    },
}));

vi.mock('@/lib/api/faces', () => api);

function face(
    i: number,
    identity?: string,
    origin?: FaceIdentityOrigin
): AssetFace {
    return {
        'id': `face-${i}`,
        '@id': `/asset-faces/face-${i}`,
        '@type': 'asset-face',
        'asset': {
            id: `asset-${i}`,
            name: `Photo ${i}`,
            preview: {file: {url: `https://cdn.test/p${i}.jpg`}},
        },
        'box': {x: 0.1, y: 0.1, w: 0.2, h: 0.3},
        'position': 0,
        'confidence': 0.9,
        'identity': identity,
        'identityOrigin': origin,
        'identityConfidence': origin ? 0.8 : undefined,
        'createdAt': '2026-10-01T10:00:00+00:00',
        'updatedAt': '2026-10-01T10:00:00+00:00',
    } as unknown as AssetFace;
}

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
        auth.roles = ['edit_faces'];
        api.getFaces.mockResolvedValue({
            total: 3,
            items: [
                face(1),
                face(2, 'Alice', FaceIdentityOrigin.User),
                face(3, 'Alice', FaceIdentityOrigin.Auto),
            ],
        });
        api.getFaceIdentities.mockResolvedValue({
            identities: [{identity: 'Alice', faces: 2, userFaces: 1}],
            unnamed: 1,
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
        expect(api.getFaces).toHaveBeenCalledWith(
            expect.objectContaining({
                workspace: undefined,
                identity: undefined,
                identified: undefined,
            }),
            expect.anything()
        );
    });

    it('filters the faces of a person or the unnamed ones', async () => {
        renderScreen();
        await screen.findAllByTestId('face-card');

        fireEvent.click(screen.getByTestId('faces-person'));
        await waitFor(() =>
            expect(api.getFaces).toHaveBeenLastCalledWith(
                expect.objectContaining({identity: 'Alice'}),
                expect.anything()
            )
        );

        fireEvent.click(screen.getByTestId('faces-person-unnamed'));
        await waitFor(() =>
            expect(api.getFaces).toHaveBeenLastCalledWith(
                expect.objectContaining({
                    identity: undefined,
                    identified: false,
                }),
                expect.anything()
            )
        );
    });

    it('names a face', async () => {
        api.identifyFace.mockResolvedValue(
            face(1, 'Bob', FaceIdentityOrigin.User)
        );
        renderScreen();
        const cards = await screen.findAllByTestId('face-card');

        const input = cards[0].querySelector(
            '[data-testid=face-name]'
        ) as HTMLInputElement;
        fireEvent.change(input, {target: {value: 'Bob'}});
        fireEvent.submit(cards[0]);

        await waitFor(() =>
            expect(api.identifyFace).toHaveBeenCalledWith('face-1', 'Bob')
        );
        await waitFor(() => expect(cards[0].textContent).toContain('Named'));
        expect(api.getFaceIdentities).toHaveBeenCalledTimes(2);
    });

    it('confirms a recognized face and removes a name', async () => {
        api.identifyFace.mockResolvedValue(
            face(3, 'Alice', FaceIdentityOrigin.User)
        );
        renderScreen();
        const cards = await screen.findAllByTestId('face-card');

        fireEvent.click(
            cards[2].querySelector('[data-testid=face-save]') as HTMLElement
        );
        await waitFor(() =>
            expect(api.identifyFace).toHaveBeenCalledWith('face-3', 'Alice')
        );

        fireEvent.click(
            cards[1].querySelector('[data-testid=face-clear]') as HTMLElement
        );
        await waitFor(() =>
            expect(api.identifyFace).toHaveBeenCalledWith('face-2', null)
        );
    });

    it('is reserved to the face editors', async () => {
        auth.roles = [];
        renderScreen();

        expect(await screen.findByTestId('faces-forbidden')).toBeTruthy();
        expect(api.getFaces).not.toHaveBeenCalled();
    });
});
