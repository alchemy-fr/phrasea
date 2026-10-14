import {describe, expect, it, vi} from 'vitest';
import {render, screen} from '@testing-library/react';
import {columnCount, Masonry} from './masonry';

describe('Masonry', () => {
    it.each([
        [0, 1],
        [200, 1],
        [412, 2],
        [411, 1],
        [1000, 4],
    ])('fits the columns in %ipx', (width, count) => {
        expect(columnCount(width, 200, 12)).toBe(count);
    });

    it('deals the items to the columns in turn', () => {
        // jsdom has no layout: 300px leave room for 2 columns of 100px
        const width = vi
            .spyOn(HTMLElement.prototype, 'clientWidth', 'get')
            .mockReturnValue(300);
        render(
            <Masonry
                items={['a', 'b', 'c']}
                columnWidth={100}
                getKey={i => i}
                renderItem={i => <span data-testid="item">{i}</span>}
            />
        );

        // Column by column: a and c in the first one, b in the second
        expect(screen.getAllByTestId('item').map(e => e.textContent)).toEqual([
            'a',
            'c',
            'b',
        ]);
        width.mockRestore();
    });
});
