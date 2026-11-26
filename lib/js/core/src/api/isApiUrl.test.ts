import {describe, expect, it} from 'vitest';
import {isApiUrl} from './isApiUrl';

describe('isApiUrl', () => {
    const apiUrl = 'https://api.test';

    it('accepts the origin of the API', () => {
        expect(isApiUrl('https://api.test/assets?page=2', apiUrl)).toBe(true);
        expect(isApiUrl('https://api.test', `${apiUrl}/`)).toBe(true);
        expect(isApiUrl('https://api.test/assets', 'https://api.test/v1')).toBe(
            true
        );
    });

    it('rejects another host', () => {
        expect(isApiUrl('https://s3.test/bucket/file', apiUrl)).toBe(false);
        expect(isApiUrl('https://api.test.evil.com/x', apiUrl)).toBe(false);
    });

    it('rejects another port', () => {
        expect(isApiUrl('https://api.test:8443/assets', apiUrl)).toBe(false);
    });

    it('rejects another scheme', () => {
        expect(isApiUrl('http://api.test/assets', apiUrl)).toBe(false);
    });

    it('resolves a relative API URL against the page', () => {
        const origin = globalThis.location.origin;
        expect(isApiUrl(`${origin}/api/assets`, '/api')).toBe(true);
        expect(isApiUrl('https://s3.test/api/assets', '/api')).toBe(false);
    });

    it('rejects an invalid URL', () => {
        expect(isApiUrl('/assets', apiUrl)).toBe(false);
        expect(isApiUrl('not a url', apiUrl)).toBe(false);
        expect(isApiUrl('https://api.test/assets', 'http://[')).toBe(false);
    });
});
