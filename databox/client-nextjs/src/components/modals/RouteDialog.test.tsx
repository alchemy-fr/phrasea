import React from 'react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, render, screen} from '@testing-library/react';
import {RouteDialog, RouteHistoryProvider, useCloseRoute} from './RouteDialog';
import {modalExitDuration} from './ModalProvider';

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

function Closer({onReady}: {onReady: (close: () => void) => void}) {
    onReady(useCloseRoute());

    return null;
}

/** Renders the screen at `nav`, with the dialog mounted or not. */
function harness(dialog: boolean, onReady: (close: () => void) => void) {
    return (
        <RouteHistoryProvider>
            {dialog ? <Closer onReady={onReady} /> : null}
        </RouteHistoryProvider>
    );
}

function navigate(
    rerender: (ui: React.ReactElement) => void,
    url: string,
    dialog: boolean,
    onReady: (close: () => void) => void
) {
    const [pathname, search = ''] = url.split('?');
    nav.pathname = pathname;
    nav.search = search;
    act(() => rerender(harness(dialog, onReady)));
}

beforeEach(() => {
    nav.pathname = '/assets';
    nav.search = '';
    nav.push.mockClear();
});

describe('useCloseRoute', () => {
    it('pushes the screen the dialog was opened from', () => {
        let close!: () => void;
        const onReady = (c: () => void) => (close = c);
        nav.search = 'query=cats';
        const {rerender} = render(harness(false, onReady));

        navigate(rerender, '/assets/1/manage/info', true, onReady);
        act(() => close());

        expect(nav.push).toHaveBeenCalledWith('/assets?query=cats', {
            scroll: false,
        });
    });

    it('keeps that destination when the dialog mirrors its tab in the URL', () => {
        let close!: () => void;
        const onReady = (c: () => void) => (close = c);
        const {rerender} = render(harness(false, onReady));

        navigate(rerender, '/assets/1/manage/info', true, onReady);
        // Switching tab rewrites the URL under the open dialog
        navigate(rerender, '/assets/1/manage/tags', true, onReady);
        act(() => close());

        expect(nav.push).toHaveBeenCalledWith('/assets', {scroll: false});
    });

    it('falls back to the assets screen when opened from a direct link', () => {
        let close!: () => void;
        nav.pathname = '/assets/1/manage/info';
        render(harness(true, c => (close = c)));
        act(() => close());

        expect(nav.push).toHaveBeenCalledWith('/assets', {scroll: false});
    });
});

/** The app at `nav.pathname`: the search screen, or one of two dialogs. */
function App() {
    const path = nav.pathname;
    const dialog = path.startsWith('/assets/a/manage')
        ? '/assets/a/manage'
        : path.startsWith('/files/f/manage')
          ? '/files/f/manage'
          : null;

    return (
        <RouteHistoryProvider>
            {dialog ? (
                // A different route remounts the dialog, as Next does
                <RouteDialog key={dialog} routeKey={dialog}>
                    {dialog}
                </RouteDialog>
            ) : null}
        </RouteHistoryProvider>
    );
}

describe('RouteDialog', () => {
    it('does not bounce between a dialog and the one opened from it', () => {
        vi.useFakeTimers();
        try {
            const {rerender} = render(<App />);
            const go = (url: string) => {
                const [pathname, search = ''] = url.split('?');
                nav.pathname = pathname;
                nav.search = search;
                act(() => rerender(<App />));
            };
            const closeAndFollow = () => {
                nav.push.mockClear();
                act(() => screen.getByRole('button', {name: 'Close'}).click());
                act(() => vi.advanceTimersByTime(modalExitDuration));
                expect(nav.push).toHaveBeenCalledOnce();
                const target = nav.push.mock.calls[0][0] as string;
                go(target);

                return target;
            };

            go('/assets?query=cats');
            go('/assets/a/manage/open');
            go('/files/f/manage/info');

            expect(closeAndFollow()).toBe('/assets/a/manage/open');
            expect(closeAndFollow()).toBe('/assets?query=cats');
        } finally {
            vi.useRealTimers();
        }
    });
});
