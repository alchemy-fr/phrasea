import {describe, expect, it} from 'vitest';
import {routes} from '@/lib/routes';
import {redactUrl} from './redactUrl';

describe('redactUrl', () => {
    it('masks the token of an upload link', () => {
        expect(redactUrl(routes.uploadLink('secret'))).toBe('/l/[token]');
        expect(redactUrl('https://uploader.test/l/secret?lang=fr')).toBe(
            'https://uploader.test/l/[token]?lang=fr'
        );
    });

    it('keeps the other pages', () => {
        expect(redactUrl(routes.uploadLinks())).toBe('/admin/upload-links');
        expect(redactUrl(routes.upload('target-1'))).toBe('/upload/target-1');
    });

    it('drops the query of the OAuth callback', () => {
        expect(redactUrl('/auth/callback?code=secret')).toBe('/auth/callback');
    });
});
