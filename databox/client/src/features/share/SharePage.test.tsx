import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ModalProvider} from '@alchemy/ui/modals/ModalProvider';
import {ApiError} from '@alchemy/ui/utils/errors';
import type {Asset, Share} from '@/types/api';
import {SharePage} from './SharePage';

const api = vi.hoisted(() => ({
    getPublicShare: vi.fn(),
    unlockShare: vi.fn(),
}));
vi.mock('@/lib/api/misc', () => api);
const nav = vi.hoisted(() => ({search: ''}));
vi.mock('next/navigation', () => ({
    useSearchParams: () => new URLSearchParams(nav.search),
    usePathname: () => '/s/s1/tok',
    useRouter: () => ({push: vi.fn(), replace: vi.fn(), prefetch: vi.fn()}),
}));
vi.mock('@/components/layout/TopBar', () => ({
    TopBar: ({children}: {children?: React.ReactNode}) => (
        <header data-testid="top-bar">{children}</header>
    ),
}));
vi.mock('./ShareAssetView', () => ({
    ShareAssetView: ({asset}: {asset: Asset}) => (
        <div data-testid="share-asset-view">{asset.id}</div>
    ),
}));

const shared = {
    id: 's1',
    token: 'tok',
    assets: [{id: 'a1', name: 'Photo.jpg'}],
    alternateUrls: [],
    attachments: [],
    terms: null,
    logo: null,
} as unknown as Share;

function passwordRequired(): ApiError {
    return new ApiError('Forbidden', 403, {error: 'share_password_required'});
}

function renderPage() {
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
                    <ModalProvider>
                        <SharePage id="s1" token="tok" />
                    </ModalProvider>
                </I18nextProvider>
            </QueryClientProvider>
        );
    });
}

async function submitPassword(value: string) {
    fireEvent.change(await screen.findByLabelText('Password'), {
        target: {value},
    });
    fireEvent.click(screen.getByText('Open'));
}

describe('SharePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        sessionStorage.clear();
        nav.search = '';
    });
    afterEach(cleanup);

    it('asks the password of a protected share, then opens it with the access token', async () => {
        api.getPublicShare.mockImplementation(
            async (_id: string, _token: string, access?: string) => {
                if (access !== 'granted') {
                    throw passwordRequired();
                }

                return shared;
            }
        );
        api.unlockShare
            .mockRejectedValueOnce(
                new ApiError('Forbidden', 403, {error: 'invalid_password'})
            )
            .mockRejectedValueOnce(
                new ApiError('Too Many Requests', 429, {
                    error: 'too_many_attempts',
                })
            )
            .mockResolvedValueOnce({
                access: 'granted',
                expiresAt: new Date(Date.now() + 3600_000).toISOString(),
            });
        renderPage();

        expect(await screen.findByTestId('share-password-form')).toBeTruthy();
        // Nothing of the share before the password
        expect(screen.queryByTestId('share-logo')).toBeNull();
        expect(api.getPublicShare).toHaveBeenCalledWith('s1', 'tok', undefined);

        await submitPassword('wrong');
        expect(await screen.findByText('Wrong password')).toBeTruthy();
        expect(api.unlockShare).toHaveBeenCalledWith('s1', 'tok', 'wrong');

        await submitPassword('again');
        expect(
            await screen.findByText(
                'Too many attempts. Please try again later.'
            )
        ).toBeTruthy();

        await submitPassword('s3cr3t');
        expect(
            (await screen.findByTestId('share-asset-view')).textContent
        ).toBe('a1');
        expect(api.getPublicShare).toHaveBeenLastCalledWith(
            's1',
            'tok',
            'granted'
        );
        expect(
            JSON.parse(sessionStorage.getItem('share.access.s1')!).access
        ).toBe('granted');
    });

    it('reuses the access token of the browser tab', async () => {
        sessionStorage.setItem(
            'share.access.s1',
            JSON.stringify({
                access: 'granted',
                expiresAt: new Date(Date.now() + 3600_000).toISOString(),
            })
        );
        api.getPublicShare.mockResolvedValue(shared);
        renderPage();

        expect(await screen.findByTestId('share-asset-view')).toBeTruthy();
        expect(api.getPublicShare).toHaveBeenCalledTimes(1);
        expect(api.getPublicShare).toHaveBeenCalledWith('s1', 'tok', 'granted');
        expect(screen.queryByTestId('share-password-form')).toBeNull();
    });

    it('forgets an expired or revoked access token', async () => {
        sessionStorage.setItem(
            'share.access.s1',
            JSON.stringify({
                access: 'revoked',
                expiresAt: new Date(Date.now() + 3600_000).toISOString(),
            })
        );
        api.getPublicShare.mockRejectedValue(passwordRequired());
        renderPage();

        expect(await screen.findByTestId('share-password-form')).toBeTruthy();
        expect(api.getPublicShare).toHaveBeenCalledWith('s1', 'tok', 'revoked');
        await waitFor(() =>
            expect(sessionStorage.getItem('share.access.s1')).toBeNull()
        );
    });

    it('asks the password in an embedded page too', async () => {
        nav.search = 'embed=1';
        api.getPublicShare.mockRejectedValue(passwordRequired());
        renderPage();

        expect(await screen.findByTestId('share-password-form')).toBeTruthy();
        expect(screen.queryByTestId('top-bar')).toBeNull();
    });

    it('keeps the invalid link page for any other error', async () => {
        api.getPublicShare.mockRejectedValue(
            new ApiError('Unauthorized', 401, {})
        );
        renderPage();

        expect(
            await screen.findByText('This link is not valid or has expired')
        ).toBeTruthy();
        expect(screen.queryByTestId('share-password-form')).toBeNull();
    });
});
