import {beforeEach, describe, expect, it, vi} from 'vitest';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {type AuthContextValue, useAuth} from './AuthProvider';
import {RequireAuth} from './RequireAuth';

let searchParams = new URLSearchParams();

vi.mock('next/navigation', () => ({
    useSearchParams: () => searchParams,
}));

vi.mock('./AuthProvider', async importOriginal => ({
    ...(await importOriginal<typeof import('./AuthProvider')>()),
    useAuth: vi.fn(),
}));

function mockAuth(auth: Partial<AuthContextValue>) {
    const login = vi.fn();
    vi.mocked(useAuth).mockReturnValue({
        status: 'anonymous',
        user: undefined,
        isAuthenticated: false,
        login,
        redirecting: false,
        logout: vi.fn(),
        loggingOut: false,
        sessionExpired: false,
        dismissSessionExpired: vi.fn(),
        ...auth,
    });

    return login;
}

function renderGate() {
    return render(
        <I18nextProvider i18n={createI18n('en')}>
            <RequireAuth>
                <p>Private</p>
            </RequireAuth>
        </I18nextProvider>
    );
}

describe('RequireAuth', () => {
    beforeEach(() => {
        searchParams = new URLSearchParams();
    });

    it('shows the private screen to an authenticated user', () => {
        const login = mockAuth({
            status: 'authenticated',
            isAuthenticated: true,
        });
        renderGate();

        expect(screen.getByText('Private')).toBeTruthy();
        expect(login).not.toHaveBeenCalled();
    });

    it('sends an anonymous user to the sign-in', () => {
        const login = mockAuth({});
        renderGate();

        expect(login).toHaveBeenCalledOnce();
        expect(screen.queryByText('Private')).toBeNull();
    });

    it('does not sign in again while the user signs out', () => {
        const login = mockAuth({loggingOut: true});
        renderGate();

        expect(login).not.toHaveBeenCalled();
        expect(screen.queryByText('Private')).toBeNull();
    });

    it('asks a user back from the sign-out to sign in', () => {
        searchParams = new URLSearchParams('logged_out=1');
        const login = mockAuth({});
        renderGate();

        expect(login).not.toHaveBeenCalled();
        expect(screen.getByTestId('logged-out')).toBeTruthy();
    });
});
