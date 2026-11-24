import {describe, expect, it} from 'vitest';
import {routes} from './routes';

describe('routes.assetView', () => {
    it('defaults to the unknown rendition', () => {
        expect(routes.assetView('a')).toBe('/assets/a/_');
    });

    it('puts the story in the query, before the hash', () => {
        expect(routes.assetView('a', 'r', '#panel=edit', 's')).toBe(
            '/assets/a/r?story=s#panel=edit'
        );
    });

    it('has no query without a story', () => {
        expect(routes.assetView('a', 'r', '#panel=edit')).toBe(
            '/assets/a/r#panel=edit'
        );
        expect(routes.assetView('a', 'r')).toBe('/assets/a/r');
    });
});
