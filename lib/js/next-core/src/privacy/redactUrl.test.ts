import {describe, expect, it} from 'vitest';
import {createUrlRedactor, redactUrl} from './redactUrl';

describe('redactUrl', () => {
    it('drops the query of the OAuth callback', () => {
        expect(redactUrl('/auth/callback?code=secret&state=s')).toBe(
            '/auth/callback'
        );
        expect(redactUrl('/auth/callback#code=secret')).toBe('/auth/callback');
        expect(
            redactUrl('https://app.test/auth/callback?code=secret&state=s')
        ).toBe('https://app.test/auth/callback');
    });

    it('keeps the other URLs', () => {
        expect(redactUrl('/assets?q=cat#top')).toBe('/assets?q=cat#top');
        expect(redactUrl('https://app.test/assets?q=cat')).toBe(
            'https://app.test/assets?q=cat'
        );
        expect(redactUrl('https://app.test')).toBe('https://app.test');
        expect(redactUrl('')).toBe('');
    });
});

describe('createUrlRedactor', () => {
    const redact = createUrlRedactor([
        {pattern: /^\/l\/[^/?#]+/, replacement: '/l/[token]'},
        {pattern: /^(\/s\/[^/]+\/)[^/?#]+/, replacement: '$1[token]'},
    ]);

    it('masks the paths of the client', () => {
        expect(redact('/l/secret')).toBe('/l/[token]');
        expect(redact('/l/secret?lang=fr#x')).toBe('/l/[token]?lang=fr#x');
        expect(redact('/s/share-1/secret/asset-1')).toBe(
            '/s/share-1/[token]/asset-1'
        );
    });

    it('masks the absolute URLs, keeping their origin', () => {
        expect(redact('https://uploader.test:8443/l/secret')).toBe(
            'https://uploader.test:8443/l/[token]'
        );
        expect(redact('http://localhost/l/secret?x=1')).toBe(
            'http://localhost/l/[token]?x=1'
        );
    });

    it('matches the paths only from their start', () => {
        expect(redact('/admin/l/secret')).toBe('/admin/l/secret');
        expect(redact('https://l.test/x/l/secret')).toBe(
            'https://l.test/x/l/secret'
        );
    });

    it('keeps the common redactions', () => {
        expect(redact('/auth/callback?code=secret')).toBe('/auth/callback');
    });
});
