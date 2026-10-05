import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {createI18n} from '@/i18n';
import {SwitchUserDialog} from './SwitchUserDialog';

const {api, auth} = vi.hoisted(() => ({
    api: {
        getImpersonableUsers: vi.fn(),
        getImpersonationIdentity: vi.fn(),
    },
    auth: {
        impersonate: vi.fn(),
    },
}));

vi.mock('@/lib/api/impersonation', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...api,
}));

const admin = {id: 'admin-id', username: 'admin', roles: ['admin'], groups: []};

vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({
        user: admin,
        realUser: admin,
        impersonate: auth.impersonate,
    }),
}));

// Used by cmdk, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => {};

function renderDialog() {
    act(() => {
        render(
            <QueryClientProvider client={new QueryClient()}>
                <I18nextProvider i18n={createI18n('en')}>
                    <SwitchUserDialog open onOpenChange={() => {}} />
                </I18nextProvider>
            </QueryClientProvider>
        );
    });
}

describe('SwitchUserDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.getImpersonableUsers.mockImplementation((query: string) =>
            Promise.resolve(
                [
                    {id: 'admin-id', username: 'admin', enabled: true},
                    {
                        id: 'alice-id',
                        username: 'alice',
                        firstName: 'Alice',
                        lastName: 'Liddell',
                        email: 'alice@phrasea.test',
                        enabled: true,
                    },
                ].filter(u => !query || u.username.includes(query))
            )
        );
        api.getImpersonationIdentity.mockResolvedValue({
            id: 'alice-id',
            username: 'alice',
            roles: ['databox'],
            groups: [],
        });
    });

    it('searches users and switches to the selected one', async () => {
        renderDialog();

        expect(await screen.findByText('Alice Liddell')).toBeTruthy();
        expect(screen.getByText('(you)')).toBeTruthy();

        fireEvent.change(
            screen.getByPlaceholderText('Search by name, username or email…'),
            {target: {value: 'ali'}}
        );
        await waitFor(() =>
            expect(api.getImpersonableUsers).toHaveBeenLastCalledWith(
                'ali',
                expect.anything()
            )
        );
        await waitFor(() => expect(screen.queryByText('(you)')).toBeNull());

        fireEvent.click(screen.getByTestId('switch-user-alice'));

        await waitFor(() =>
            expect(auth.impersonate).toHaveBeenCalledWith({
                id: 'alice-id',
                username: 'alice',
                roles: ['databox'],
                groups: [],
            })
        );
        expect(api.getImpersonationIdentity).toHaveBeenCalledWith('alice-id');
    });

    it('does nothing when picking the current user', async () => {
        renderDialog();

        fireEvent.click(await screen.findByTestId('switch-user-admin'));

        expect(auth.impersonate).not.toHaveBeenCalled();
        expect(api.getImpersonationIdentity).not.toHaveBeenCalled();
    });
});
