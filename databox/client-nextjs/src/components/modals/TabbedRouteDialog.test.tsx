import {beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {RouteHistoryProvider} from './RouteDialog';
import {DialogTab, TabbedRouteDialogShell} from './TabbedRouteDialog';

const nav = vi.hoisted(() => ({
    pathname: '/workspaces/1/manage/info',
    push: vi.fn(),
    renders: {info: 0, tags: 0} as Record<string, number>,
}));

vi.mock('next/navigation', () => ({
    usePathname: () => nav.pathname,
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: nav.push}),
}));

type Props = {name: string};

function tabComponent(id: string) {
    return function Tab({name}: Props) {
        nav.renders[id] = (nav.renders[id] ?? 0) + 1;

        return (
            <span>
                {id} of {name}
            </span>
        );
    };
}

const tabs: DialogTab<Props>[] = [
    {id: 'info', title: 'Info', component: tabComponent('info')},
    {id: 'tags', title: 'Tags', component: tabComponent('tags')},
    {
        id: 'hidden',
        title: 'Hidden',
        component: tabComponent('hidden'),
        enabled: false,
    },
];

// Stable, as the manage shells provide it (`useMemo`)
const baseProps: Props = {name: 'ws'};

function Shell() {
    return (
        <RouteHistoryProvider>
            <TabbedRouteDialogShell
                title="Manage workspace"
                tabs={tabs}
                baseProps={baseProps}
                buildTabHref={tab => `/workspaces/1/manage/${tab}`}
            />
        </RouteHistoryProvider>
    );
}

function selectTab(name: string) {
    // Radix tabs activate on mouse down, not on click
    act(() =>
        fireEvent.mouseDown(screen.getByRole('tab', {name}), {button: 0})
    );
}

beforeEach(() => {
    nav.pathname = '/workspaces/1/manage/info';
    nav.push.mockClear();
    nav.renders = {};
    vi.restoreAllMocks();
});

describe('TabbedRouteDialogShell', () => {
    it('does not re-render the tabs already visited when switching', () => {
        const {rerender} = render(<Shell />);
        const go = (tab: string) => {
            nav.pathname = `/workspaces/1/manage/${tab}`;
            act(() => rerender(<Shell />));
        };

        go('tags');
        go('info');
        go('tags');

        // Each tab rendered once, when first shown
        expect(nav.renders).toEqual({info: 1, tags: 1});
    });

    it('switches tabs with a history entry, without navigating', () => {
        const pushState = vi.spyOn(window.history, 'pushState');
        act(() => {
            render(<Shell />);
        });

        selectTab('Tags');

        expect(pushState).toHaveBeenCalledOnce();
        // `null`: Next ignores (does not sync) states carrying its own markers
        expect(pushState.mock.calls[0][0]).toBeNull();
        expect(pushState.mock.calls[0][2]).toBe('/workspaces/1/manage/tags');
        expect(nav.push).not.toHaveBeenCalled();
    });

    it('shows the tab of the URL and keeps visited tabs mounted', () => {
        const {rerender} = render(<Shell />);
        expect(screen.getByTestId('dialog-tab-info').hidden).toBe(false);
        expect(screen.queryByTestId('dialog-tab-tags')).toBeNull();

        // Next syncs usePathname with the pushed URL
        nav.pathname = '/workspaces/1/manage/tags';
        act(() => rerender(<Shell />));
        expect(screen.getByTestId('dialog-tab-tags').hidden).toBe(false);
        expect(screen.getByTestId('dialog-tab-info').hidden).toBe(true);

        // Back to info: shown again, not remounted
        const infoNode = screen.getByTestId('dialog-tab-info').firstChild;
        nav.pathname = '/workspaces/1/manage/info';
        act(() => rerender(<Shell />));
        expect(screen.getByTestId('dialog-tab-info').hidden).toBe(false);
        expect(screen.getByTestId('dialog-tab-info').firstChild).toBe(infoNode);
    });

    it('leaves out disabled tabs, even when the URL points at one', () => {
        nav.pathname = '/workspaces/1/manage/hidden';
        render(<Shell />);

        expect(screen.queryByRole('tab', {name: 'Hidden'})).toBeNull();
        expect(screen.getByTestId('dialog-tab-info').hidden).toBe(false);
    });
});
