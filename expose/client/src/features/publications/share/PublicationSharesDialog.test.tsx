import {beforeEach, describe, expect, it, vi} from 'vitest';
import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {I18nextProvider} from 'react-i18next';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@alchemy/next-ui/modals/ModalProvider';
import {ShareStatus, type PublicationShare} from '@/types/api';
import {PublicationSharesDialog} from './PublicationSharesDialog';

const api = vi.hoisted(() => ({
    getPublicationShares: vi.fn(),
    createPublicationShare: vi.fn(),
    updatePublicationShare: vi.fn(),
    revokePublicationShare: vi.fn(),
    deletePublicationShare: vi.fn(),
}));
vi.mock('@/lib/api/shares', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...api,
}));
vi.mock('sonner', () => ({toast: {success: vi.fn(), error: vi.fn()}}));
vi.mock('next/navigation', () => ({
    usePathname: () => '/pub',
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

function share(
    id: string,
    extra: Partial<PublicationShare> = {}
): PublicationShare {
    return {
        id,
        name: `Link ${id}`,
        token: `token${id}`,
        url: `https://expose.example/s/token${id}`,
        status: ShareStatus.Active,
        expiresAt: null,
        createdAt: '2026-01-01T00:00:00Z',
        ...extra,
    };
}

function renderDialog() {
    act(() => {
        render(
            <QueryClientProvider
                client={
                    new QueryClient({
                        defaultOptions: {queries: {retry: false}},
                    })
                }
            >
                <I18nextProvider i18n={createI18n('en')}>
                    <TooltipPrimitive.Provider>
                        <ModalProvider>
                            <PublicationSharesDialog
                                open
                                onOpenChange={() => undefined}
                                publication={{id: 'p1', title: 'Summer'}}
                            />
                        </ModalProvider>
                    </TooltipPrimitive.Provider>
                </I18nextProvider>
            </QueryClientProvider>
        );
    });
}

describe('PublicationSharesDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('lists the links with their state and permalink', async () => {
        api.getPublicationShares.mockResolvedValue([
            share('1', {expiresAt: '2030-01-01T00:00:00Z'}),
            share('2', {
                name: null,
                status: ShareStatus.Revoked,
                revokedAt: '2026-02-01T00:00:00Z',
            }),
        ]);
        renderDialog();

        const items = await screen.findAllByTestId('share-item');
        expect(items).toHaveLength(2);
        expect(items[0].textContent).toContain('Link 1');
        expect(items[0].textContent).toContain('Active');
        expect(items[0].textContent).toContain('Until');
        expect(within(items[0]).getByTestId('share-url').textContent).toBe(
            'https://expose.example/s/token1'
        );
        expect(within(items[0]).queryByTestId('share-delete')).toBeNull();

        expect(items[1].textContent).toContain('Share link');
        expect(items[1].textContent).toContain('Revoked');
        expect(items[1].textContent).toContain('Revoked on');
        expect(within(items[1]).queryByTestId('share-revoke')).toBeNull();
        expect(within(items[1]).queryByTestId('share-edit')).toBeNull();
        expect(within(items[1]).getByTestId('share-delete')).toBeTruthy();
    });

    it('creates a link expiring in 30 days by default, or never', async () => {
        api.getPublicationShares.mockResolvedValue([]);
        let created = 0;
        api.createPublicationShare.mockImplementation(async () =>
            share(`new${++created}`)
        );
        renderDialog();
        expect(await screen.findByTestId('share-empty')).toBeTruthy();

        const expiresAt = screen.getByLabelText(
            'Expires on'
        ) as HTMLInputElement;
        const days =
            (new Date(expiresAt.value).getTime() - Date.now()) / 86_400_000;
        expect(Math.round(days)).toBe(30);

        fireEvent.change(screen.getByLabelText('Name'), {
            target: {value: ' For John '},
        });
        fireEvent.click(screen.getByText('Create link'));
        await waitFor(() =>
            expect(api.createPublicationShare).toHaveBeenCalledTimes(1)
        );
        const [publicationId, data] = api.createPublicationShare.mock.calls[0];
        expect(publicationId).toBe('p1');
        expect(data.name).toBe('For John');
        expect(new Date(data.expiresAt).getTime()).toBeGreaterThan(Date.now());

        // The dialog stays open with the new link
        expect((await screen.findAllByTestId('share-item')).length).toBe(1);
        expect((screen.getByLabelText('Name') as HTMLInputElement).value).toBe(
            ''
        );

        fireEvent.click(screen.getByTestId('share-expires-at-clear'));
        fireEvent.click(screen.getByText('Create link'));
        await waitFor(() =>
            expect(api.createPublicationShare).toHaveBeenCalledTimes(2)
        );
        expect(api.createPublicationShare.mock.calls[1][1]).toEqual({
            name: null,
            expiresAt: null,
        });
    });

    it('revokes a link once confirmed', async () => {
        api.getPublicationShares.mockResolvedValue([share('1')]);
        api.revokePublicationShare.mockResolvedValue(
            share('1', {
                status: ShareStatus.Revoked,
                revokedAt: '2026-02-01T00:00:00Z',
            })
        );
        renderDialog();

        fireEvent.click(await screen.findByTestId('share-revoke'));
        await waitFor(() =>
            expect(screen.getAllByTestId('form-dialog')).toHaveLength(2)
        );
        const confirm = screen.getAllByTestId('form-dialog')[1];
        fireEvent.click(within(confirm).getByText('Revoke'));
        await waitFor(() =>
            expect(api.revokePublicationShare).toHaveBeenCalledWith('1')
        );
        await waitFor(() =>
            expect(
                screen.getByTestId('share-item').getAttribute('data-status')
            ).toBe(ShareStatus.Revoked)
        );
    });
});
