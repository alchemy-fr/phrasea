import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {RouteHistoryProvider} from './RouteDialog';
import {TabbedRouteDialogShell} from './TabbedRouteDialog';

const nav = vi.hoisted(() => ({
    pathname: '/workspaces/1/manage/info',
    search: '',
    segment: 'info',
    push: vi.fn(),
    replace: vi.fn(),
}));

vi.mock('next/navigation', () => ({
    usePathname: () => nav.pathname,
    useSearchParams: () => new URLSearchParams(nav.search),
    useSelectedLayoutSegment: () => nav.segment,
    useRouter: () => ({push: nav.push, replace: nav.replace}),
}));

const tabs = [
    {id: 'info', title: 'Info'},
    {id: 'tags', title: 'Tags'},
    {id: 'hidden', title: 'Hidden', enabled: false},
];

function renderShell() {
    act(() => {
        render(
            <RouteHistoryProvider>
                <TabbedRouteDialogShell
                    title="Manage workspace"
                    tabs={tabs}
                    buildTabHref={tab => `/workspaces/1/manage/${tab}`}
                >
                    <span>tab content</span>
                </TabbedRouteDialogShell>
            </RouteHistoryProvider>
        );
    });
}

beforeEach(() => {
    nav.segment = 'info';
    nav.push.mockClear();
    nav.replace.mockClear();
    vi.restoreAllMocks();
});

describe('TabbedRouteDialogShell', () => {
    it('pushes a history entry for the tab instead of rewriting the URL', () => {
        const replaceState = vi.spyOn(window.history, 'replaceState');
        renderShell();

        // Radix tabs activate on mouse down, not on click
        act(() =>
            fireEvent.mouseDown(screen.getByRole('tab', {name: 'Tags'}), {
                button: 0,
            })
        );

        expect(nav.push).toHaveBeenCalledWith('/workspaces/1/manage/tags', {
            scroll: false,
        });
        // A shallow rewrite would leave the router tree on the previous tab
        expect(replaceState).not.toHaveBeenCalled();
        expect(nav.replace).not.toHaveBeenCalled();
    });

    it('marks the tab of the active route segment', () => {
        nav.segment = 'tags';
        renderShell();

        expect(screen.getByRole('tab', {name: 'Tags'})).toHaveProperty(
            'ariaSelected',
            'true'
        );
    });

    it('leaves out disabled tabs', () => {
        renderShell();

        expect(screen.queryByRole('tab', {name: 'Hidden'})).toBeNull();
    });
});
