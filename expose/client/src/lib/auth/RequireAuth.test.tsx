import {beforeEach, describe, expect, it, vi} from 'vitest';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {type AuthContextValue, useAuth} from './AuthProvider';
import {RequireAuth} from './RequireAuth';
import {hasLoggedOut} from './loggedOut';

vi.mock('./AuthProvider', async importOriginal => ({
    ...(await importOriginal<typeof import('./AuthProvider')>()),
    useAuth: vi.fn(),
}));

vi.mock('./loggedOut', () => ({hasLoggedOut: vi.fn()}));

function mockAuth(auth: Partial<AuthContextValue>) {
    const login = vi.fn();
    vi.mocked(useAuth).mockReturnValue({
        status: 'anonymous',
        user: undefined,
        isAuthenticated: false,
        login,
        loginInWindow: vi.fn(),
        redirecting: false,
        logout: vi.fn(),
        loggingOut: false,
        hasRole: () => false,
        sessionExpired: false,
        dismissSessionExpired: vi.fn(),
        ...auth,
    });

    return login;
}

function gate() {
    return (
        <I18nextProvider i18n={createI18n('en')}>
            <RequireAuth>
                <p>Private</p>
            </RequireAuth>
        </I18nextProvider>
    );
}

describe('RequireAuth', () => {
    beforeEach(() => {
        vi.mocked(hasLoggedOut).mockReturnValue(false);
    });

    it('shows the private screen to an authenticated user', () => {
        const login = mockAuth({
            status: 'authenticated',
            isAuthenticated: true,
        });
        render(gate());

        expect(screen.getByText('Private')).toBeTruthy();
        expect(login).not.toHaveBeenCalled();
    });

    it('sends an anonymous user to the sign-in', () => {
        const login = mockAuth({});
        render(gate());

        expect(login).toHaveBeenCalledOnce();
        expect(screen.queryByText('Private')).toBeNull();
    });

    it('does not sign in again while the user signs out', () => {
        mockAuth({status: 'authenticated', isAuthenticated: true});
        const {rerender} = render(gate());

        // The sign-out flag is stored once the gate is mounted
        vi.mocked(hasLoggedOut).mockReturnValue(true);
        const login = mockAuth({loggingOut: true});
        rerender(gate());

        expect(login).not.toHaveBeenCalled();
        expect(screen.queryByText('Private')).toBeNull();
    });

    it('asks a user back from the sign-out to sign in', () => {
        vi.mocked(hasLoggedOut).mockReturnValue(true);
        const login = mockAuth({});
        render(gate());

        expect(login).not.toHaveBeenCalled();
        expect(screen.getByText('You are signed out')).toBeTruthy();
    });
});
