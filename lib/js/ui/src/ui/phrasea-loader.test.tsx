import {StrictMode} from 'react';
import {render, screen} from '@testing-library/react';
import {afterEach, describe, expect, it, vi} from 'vitest';
import {PHRASEA_LOADER_VARIANTS, PhraseaLoader} from './phrasea-loader';

const facets = (root: HTMLElement) =>
    [...root.querySelectorAll('[data-facet]')] as SVGElement[];

afterEach(() => {
    vi.restoreAllMocks();
});

describe('PhraseaLoader', () => {
    it.each(PHRASEA_LOADER_VARIANTS)('draws the six facets (%s)', variant => {
        render(<PhraseaLoader variant={variant} label="Loading" />);
        const loader = screen.getByRole('status');

        expect(loader).toHaveProperty('ariaLabel', 'Loading');
        expect(loader.dataset.variant).toBe(variant);
        expect(facets(loader).map(f => f.dataset.facet)).toEqual([
            'orange',
            'yellow',
            'green',
            'blue',
            'red',
            'purple',
        ]);
    });

    it('lights the facets one after the other, clockwise', () => {
        render(<PhraseaLoader variant="ring" />);
        const delays = Object.fromEntries(
            facets(screen.getByRole('status')).map(f => [
                f.dataset.facet,
                f.style.animationDelay,
            ])
        );

        expect(delays).toEqual({
            yellow: '-1200ms',
            green: '-1000ms',
            blue: '-800ms',
            purple: '-600ms',
            red: '-400ms',
            orange: '-200ms',
        });
    });

    it('pushes each facet away from the center', () => {
        render(<PhraseaLoader variant="burst" />);
        const offsets = Object.fromEntries(
            facets(screen.getByRole('status')).map(f => [
                f.dataset.facet,
                [
                    f.style.getPropertyValue('--phrasea-x'),
                    f.style.getPropertyValue('--phrasea-y'),
                ],
            ])
        );

        expect(offsets.green).toEqual(['48.0px', '0.0px']);
        expect(offsets.red).toEqual(['-48.0px', '0.0px']);
        expect(offsets.yellow).toEqual(['24.0px', '-41.6px']);
        expect(offsets.purple).toEqual(['-24.0px', '41.6px']);
    });

    it('moves the two facets of a face together', () => {
        render(<PhraseaLoader variant="pistons" />);
        const delays = Object.fromEntries(
            facets(screen.getByRole('status')).map(f => [
                f.dataset.facet,
                f.style.animationDelay,
            ])
        );

        expect(delays.orange).toBe(delays.yellow);
        expect(delays.green).toBe(delays.blue);
        expect(delays.red).toBe(delays.purple);
        expect(new Set(Object.values(delays)).size).toBe(3);
    });

    it('picks an animation at random once mounted', () => {
        vi.spyOn(Math, 'random').mockReturnValue(0.999);
        render(<PhraseaLoader />);

        expect(PHRASEA_LOADER_VARIANTS).toContain(
            screen.getByRole('status').dataset.variant
        );
    });

    it('never plays the same animation twice in a row', () => {
        vi.spyOn(Math, 'random').mockReturnValue(0);
        const variants = Array.from({length: 4}, () => {
            const {unmount} = render(
                <StrictMode>
                    <PhraseaLoader />
                </StrictMode>
            );
            const {variant} = screen.getByRole('status').dataset;
            unmount();

            return variant;
        });

        variants.slice(1).forEach((variant, i) => {
            expect(variant).not.toBe(variants[i]);
        });
    });

    it('keeps the gradients of two loaders apart', () => {
        const {container} = render(
            <>
                <PhraseaLoader variant="ring" />
                <PhraseaLoader variant="ring" />
            </>
        );
        const ids = [...container.querySelectorAll('linearGradient')].map(
            g => g.id
        );

        expect(ids).toHaveLength(12);
        expect(new Set(ids).size).toBe(12);
    });
});
