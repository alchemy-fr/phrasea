import {afterEach, describe, expect, it} from 'vitest';
import {act, cleanup, render, renderHook} from '@testing-library/react';
import {
    TakeoverScreen,
    useCoveredByTakeoverScreen,
    useTakeoverScreenStore,
} from './takeoverScreens';

function Probe({name}: {name: string}) {
    const covered = useCoveredByTakeoverScreen();

    return <span data-testid={name}>{covered ? 'covered' : 'visible'}</span>;
}

describe('useCoveredByTakeoverScreen', () => {
    afterEach(() => {
        cleanup();
        useTakeoverScreenStore.setState({screens: []});
    });

    it('does not cover the base page without a takeover screen', () => {
        const page = renderHook(() => useCoveredByTakeoverScreen());

        expect(page.result.current).toBe(false);
    });

    it('covers the base page while a takeover screen is mounted', () => {
        const page = renderHook(() => useCoveredByTakeoverScreen());
        const screen = render(<TakeoverScreen />);

        expect(page.result.current).toBe(true);

        act(() => screen.unmount());

        expect(page.result.current).toBe(false);
    });

    it('only covers the screens mounted under the last one', () => {
        const page = render(<Probe name="page" />);
        const first = render(
            <TakeoverScreen>
                <Probe name="first" />
            </TakeoverScreen>
        );

        expect(first.getByTestId('first').textContent).toBe('visible');

        const second = render(
            <TakeoverScreen>
                <Probe name="second" />
            </TakeoverScreen>
        );

        expect(page.getByTestId('page').textContent).toBe('covered');
        expect(first.getByTestId('first').textContent).toBe('covered');
        expect(second.getByTestId('second').textContent).toBe('visible');

        act(() => second.unmount());

        expect(page.getByTestId('page').textContent).toBe('covered');
        expect(first.getByTestId('first').textContent).toBe('visible');

        act(() => first.unmount());

        expect(page.getByTestId('page').textContent).toBe('visible');
    });
});
