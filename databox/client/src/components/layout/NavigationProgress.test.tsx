import type {ReactElement} from 'react';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {act, render} from '@testing-library/react';
import {NavigationProgress} from './NavigationProgress';
import {notifyNavigationStart} from '@/lib/navigation/navigationProgress';

const nav = vi.hoisted(() => ({pathname: '/assets', search: ''}));

vi.mock('next/navigation', () => ({
    usePathname: () => nav.pathname,
    useSearchParams: () => new URLSearchParams(nav.search),
}));

function bar(container: HTMLElement): HTMLElement {
    return container.firstElementChild!.firstElementChild as HTMLElement;
}

function commit(
    rerender: (ui: ReactElement) => void,
    pathname: string,
    search = ''
) {
    nav.pathname = pathname;
    nav.search = search;
    act(() => rerender(<NavigationProgress />));
}

describe('NavigationProgress', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        nav.pathname = '/assets';
        nav.search = '';
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the bar while a slow navigation loads', () => {
        const {container, rerender} = render(<NavigationProgress />);
        act(() => notifyNavigationStart('/collections/1'));
        expect(bar(container).style.opacity).toBe('0');

        act(() => vi.advanceTimersByTime(1000));
        expect(bar(container).style.opacity).toBe('1');

        commit(rerender, '/collections/1');
        expect(bar(container).style.transform).toBe('scaleX(1)');
        act(() => vi.advanceTimersByTime(1000));
        expect(bar(container).style.opacity).toBe('0');
        expect(bar(container).style.transform).toBe('scaleX(0)');
    });

    it('does not flash on a navigation committed right away', () => {
        const {container, rerender} = render(<NavigationProgress />);
        act(() => notifyNavigationStart('/collections/1'));
        commit(rerender, '/collections/1');
        act(() => vi.advanceTimersByTime(1000));
        expect(bar(container).style.opacity).toBe('0');
    });

    it('ignores the navigations that only change the hash', () => {
        nav.search = 'q=a&b=1';
        const {container} = render(<NavigationProgress />);
        act(() => notifyNavigationStart('/assets?q=a&b=1#panel=info'));
        act(() => vi.advanceTimersByTime(1000));
        expect(bar(container).style.opacity).toBe('0');
    });

    it('gives up on a navigation that never commits', () => {
        const {container} = render(<NavigationProgress />);
        act(() => notifyNavigationStart('/collections/1'));
        act(() => vi.advanceTimersByTime(1000));
        expect(bar(container).style.opacity).toBe('1');
        act(() => vi.advanceTimersByTime(60_000));
        expect(bar(container).style.opacity).toBe('0');
    });
});
