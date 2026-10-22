import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {ApiError} from '@/lib/api/http';
import {RouteDialog, RouteHistoryProvider} from './RouteDialog';
import {LoadPlaceholder} from './LoadPlaceholder';

const nav = vi.hoisted(() => ({
    pathname: '/assets',
    search: '',
    push: vi.fn(),
}));

vi.mock('next/navigation', () => ({
    usePathname: () => nav.pathname,
    useSearchParams: () => new URLSearchParams(nav.search),
    useRouter: () => ({push: nav.push}),
}));

function screenAt(error: unknown, dialog: boolean, lng = 'en') {
    return (
        <I18nextProvider i18n={createI18n(lng)}>
            <RouteHistoryProvider>
                {dialog ? (
                    <RouteDialog routeKey="/workspaces/1/manage">
                        <LoadPlaceholder error={error} />
                    </RouteDialog>
                ) : null}
            </RouteHistoryProvider>
        </I18nextProvider>
    );
}

/** Opens the dialog from the search page */
function openDialog(error: unknown, lng?: string) {
    nav.pathname = '/assets';
    nav.search = 'q=cats';
    const {rerender} = render(screenAt(null, false, lng));
    nav.pathname = '/workspaces/1/manage/info';
    nav.search = '';
    act(() => rerender(screenAt(error, true, lng)));
}

beforeEach(() => {
    nav.push.mockClear();
});

describe('LoadPlaceholder', () => {
    it('shows a loader while there is no error', () => {
        openDialog(null);

        expect(screen.queryByTestId('load-error')).toBeNull();
        expect(
            screen.getByTestId('route-dialog').querySelector('.animate-spin')
        ).not.toBeNull();
    });

    it('tells the item does not exist on a 404, and closes the dialog', () => {
        openDialog(new ApiError('Not Found', 404, null));

        expect(screen.getByTestId('load-error').textContent).toContain(
            'This item does not exist (anymore).'
        );
        expect(screen.queryByText('Not Found')).toBeNull();

        const close = screen.getByTestId('load-error-close');
        expect(close.textContent).toBe('Close');
        fireEvent.click(close);
        expect(nav.push).toHaveBeenCalledWith('/assets?q=cats', {
            scroll: false,
        });
    });

    it('tells the item is not accessible on a 403', () => {
        openDialog(new ApiError('Access Denied.', 403, null), 'fr');

        expect(
            screen.getByText("Vous n'avez pas accès à cet élément.")
        ).toBeTruthy();
        expect(screen.queryByText('Access Denied.')).toBeNull();
        expect(screen.getByTestId('load-error-close').textContent).toBe(
            'Fermer'
        );
    });

    it('gives the message of any other error', () => {
        openDialog(new Error('boom'));

        expect(screen.getByText('An error occurred')).toBeTruthy();
        expect(screen.getByText('boom')).toBeTruthy();
        expect(screen.getByTestId('load-error-close')).toBeTruthy();
    });
});
