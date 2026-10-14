import {describe, expect, it} from 'vitest';
import {render, screen} from '@testing-library/react';
import {
    AssetFilmstripLoader,
    AssetGridLoader,
    AssetStackLoader,
} from './asset-loaders';

describe('asset loaders', () => {
    it('renders one tile per cell, staggered along the diagonal', () => {
        const {container} = render(
            <AssetGridLoader columns={3} rows={2} label="Loading assets" />
        );

        expect(screen.getByRole('status')).toHaveProperty(
            'ariaLabel',
            'Loading assets'
        );
        const tiles = container.querySelectorAll('.animate-asset-tile');
        expect(tiles).toHaveLength(6);
        expect((tiles[0] as HTMLElement).style.animationDelay).toBe('0ms');
        // last cell: row 1 + col 2 = 3 steps
        expect((tiles[5] as HTMLElement).style.animationDelay).toBe('270ms');
    });

    it('spreads the three stacked cards over the cycle', () => {
        const {container} = render(<AssetStackLoader />);
        const cards = [...container.querySelectorAll('.animate-asset-stack')];

        expect(cards.map(c => (c as HTMLElement).style.animationDelay)).toEqual(
            ['0ms', '-1200ms', '-2400ms']
        );
    });

    it('shows the caption only when a label is given', () => {
        render(<AssetFilmstripLoader label="Fetching…" />);
        expect(screen.getByText('Fetching…')).toBeTruthy();
        expect(
            screen.getByRole('status').querySelectorAll('.animate-asset-frame')
        ).toHaveLength(5);
    });
});
