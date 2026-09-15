import React from 'react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, render} from '@testing-library/react';
import {RouteHistoryProvider, useCloseRoute} from './RouteDialog';

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
