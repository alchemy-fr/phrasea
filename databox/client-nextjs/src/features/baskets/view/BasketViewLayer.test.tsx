import {beforeEach, describe, expect, it, vi} from 'vitest';
import {render, screen} from '@testing-library/react';
import {BasketViewLayer} from './BasketViewLayer';

const nav = vi.hoisted(() => ({pathname: '/assets'}));

vi.mock('next/navigation', () => ({
    usePathname: () => nav.pathname,
}));

vi.mock('./BasketViewRoute', () => ({
    BasketViewRoute: ({basketId}: {basketId: string}) => (
        <span data-testid="basket">{basketId}</span>
    ),
}));

function navigate(rerender: (ui: React.ReactElement) => void, path: string) {
    nav.pathname = path;
    rerender(<BasketViewLayer />);
}

const basket = () => screen.queryByTestId('basket')?.textContent;

describe('BasketViewLayer', () => {
    beforeEach(() => {
        nav.pathname = '/assets';
    });

    it('keeps the basket view behind the dialogs opened from it', () => {
        const {rerender} = render(<BasketViewLayer />);
        expect(basket()).toBeUndefined();

        navigate(rerender, '/baskets/b1/view');
        expect(basket()).toBe('b1');

        navigate(rerender, '/baskets/b1/manage/info');
        expect(basket()).toBe('b1');
        navigate(rerender, '/assets/a1/_');
        expect(basket()).toBe('b1');

        navigate(rerender, '/baskets/b2/view');
        expect(basket()).toBe('b2');

        navigate(rerender, '/assets');
        expect(basket()).toBeUndefined();

        // Nothing to keep when a dialog is not opened from a basket
        navigate(rerender, '/workspaces/w1/manage/info');
        expect(basket()).toBeUndefined();
    });

    it('closes when leaving for another page', () => {
        nav.pathname = '/baskets/b1/view';
        const {rerender} = render(<BasketViewLayer />);
        navigate(rerender, '/quarantine');
        expect(basket()).toBeUndefined();
    });
});
