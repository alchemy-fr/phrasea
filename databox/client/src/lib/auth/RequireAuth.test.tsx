import {describe, expect, it, vi} from 'vitest';
import {render, screen} from '@testing-library/react';
import {type AuthContextValue, useAuth} from './AuthProvider';
import {RequireAuth} from './RequireAuth';

vi.mock('./AuthProvider', async importOriginal => ({
    ...(await importOriginal<typeof import('./AuthProvider')>()),
    useAuth: vi.fn(),
}));

function mockAuth(auth: Partial<AuthContextValue>) {
    const login = vi.fn();
    vi.mocked(useAuth).mockReturnValue({
        status: 'anonymous',
        user: undefined,
        realUser: undefined,
        impersonating: false,
        canImpersonate: false,
        canViewOAuthClients: false,
        canManageOAuthClients: false,
        impersonate: vi.fn(),
        stopImpersonating: vi.fn(),
        isAuthenticated: false,
        login,
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
        <RequireAuth>
            <p>Private</p>
        </RequireAuth>
    );
}

describe('RequireAuth', () => {
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

        const login = mockAuth({loggingOut: true});
        rerender(gate());

        expect(login).not.toHaveBeenCalled();
        expect(screen.queryByText('Private')).toBeNull();
    });
});
