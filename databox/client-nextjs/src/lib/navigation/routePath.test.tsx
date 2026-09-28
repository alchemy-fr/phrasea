import {describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {
    joinRoutePath,
    NestedRoutePath,
    RoutePathProvider,
    splitRoutePath,
    useRoutePath,
} from './routePath';

function Show({to}: {to: string[]}) {
    const {segments, navigate} = useRoutePath();

    return (
        <button type="button" onClick={() => navigate(to)}>
            {segments.join('|') || '-'}
        </button>
    );
}

describe('routePath', () => {
    it('splits and joins URL paths', () => {
        expect(splitRoutePath('/a/b/x%20y/z', '/a/b')).toEqual(['x y', 'z']);
        expect(splitRoutePath('/a/b', '/a/b/')).toEqual([]);
        expect(splitRoutePath('/a/bc', '/a/b')).toBeNull();
        expect(joinRoutePath('/a/b/', ['x y', 'z'])).toBe('/a/b/x%20y/z');
    });

    it('keeps the path in local state without a provider', () => {
        render(<Show to={['new']} />);
        act(() => fireEvent.click(screen.getByText('-')));

        expect(screen.getByText('new')).toBeTruthy();
    });

    it('hands the segments below a prefix to a nested screen', () => {
        const navigate = vi.fn();
        const {rerender} = render(
            <RoutePathProvider
                value={{segments: ['l1', 'manage', 'e1'], navigate}}
            >
                <NestedRoutePath prefix={['l1', 'manage']}>
                    <Show to={['e2']} />
                </NestedRoutePath>
            </RoutePathProvider>
        );
        act(() => fireEvent.click(screen.getByText('e1')));
        expect(navigate).toHaveBeenCalledWith(
            ['l1', 'manage', 'e2'],
            undefined
        );

        // Not under the prefix: nothing selected below
        rerender(
            <RoutePathProvider value={{segments: ['l2'], navigate}}>
                <NestedRoutePath prefix={['l1', 'manage']}>
                    <Show to={[]} />
                </NestedRoutePath>
            </RoutePathProvider>
        );
        expect(screen.getByText('-')).toBeTruthy();
    });
});
