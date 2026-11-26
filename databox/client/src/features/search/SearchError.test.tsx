import {beforeEach, describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {AuthStatus} from '@/lib/auth/AuthProvider';
import {SearchError} from './SearchError';

const {auth} = vi.hoisted(() => ({
    auth: {
        status: 'authenticated' as AuthStatus,
        login: vi.fn(),
    },
}));

vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({
        status: auth.status,
        login: auth.login,
        redirecting: false,
    }),
}));

function renderError(
    error = 'Field "description" not found',
    timedOut?: boolean
) {
    const onRetry = vi.fn();
    render(
        <I18nextProvider i18n={createI18n('en')}>
            <SearchError error={error} timedOut={timedOut} onRetry={onRetry} />
        </I18nextProvider>
    );

    return {onRetry};
}

describe('SearchError', () => {
    beforeEach(() => {
        auth.login.mockReset();
    });

    it('only shows the error and a retry button when signed in', () => {
        auth.status = 'authenticated';
        const {onRetry} = renderError();

        expect(screen.getByText('Search failed')).toBeTruthy();
        expect(screen.getByText('Field "description" not found')).toBeTruthy();
        expect(screen.queryByTestId('search-error-timeout-hint')).toBeNull();
        expect(screen.queryByTestId('search-error-sign-in-hint')).toBeNull();
        expect(screen.queryByTestId('search-error-sign-in')).toBeNull();

        fireEvent.click(screen.getByRole('button', {name: 'Retry'}));
        expect(onRetry).toHaveBeenCalled();
    });

    it('asks anonymous users to sign in', () => {
        auth.status = 'anonymous';
        renderError();

        expect(screen.getByTestId('search-error-sign-in-hint')).toBeTruthy();
        expect(screen.getByText('Field "description" not found')).toBeTruthy();

        fireEvent.click(screen.getByTestId('search-error-sign-in'));
        expect(auth.login).toHaveBeenCalled();
    });

    it('explains a timeout and keeps the raw message and the retry button', () => {
        auth.status = 'authenticated';
        const message =
            'Request timed out: GET https://api.test/assets?query=cat';
        const {onRetry} = renderError(message, true);

        expect(screen.getByText('Search is taking too long')).toBeTruthy();
        expect(screen.queryByText('Search failed')).toBeNull();
        expect(
            screen.getByTestId('search-error-timeout-hint').textContent
        ).toBe(
            'The server did not answer in time, it may be busy. Try again in a moment.'
        );
        expect(screen.getByText(message)).toBeTruthy();

        fireEvent.click(screen.getByRole('button', {name: 'Retry'}));
        expect(onRetry).toHaveBeenCalled();
    });
});
