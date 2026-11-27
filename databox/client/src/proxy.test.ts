// @vitest-environment node
import {describe, expect, it} from 'vitest';
import {NextRequest} from 'next/server';
import {proxy} from './proxy';

function request(path: string): NextRequest {
    return new NextRequest(`https://databox.test${path}`);
}

describe('proxy', () => {
    it('redirects a notification deep link with its fragment', () => {
        const response = proxy(
            request(
                `/notification-uri?uri=${encodeURIComponent('/assets/abc#discussion-1')}`
            )
        );

        expect(response.status).toBe(307);
        expect(response.headers.get('Location')).toBe(
            'https://databox.test/assets/abc/_#discussion-1'
        );
    });

    it('redirects a notification deep link without URI to the search', () => {
        const response = proxy(request('/notification-uri'));

        expect(response.status).toBe(307);
        expect(response.headers.get('Location')).toBe(
            'https://databox.test/assets'
        );
    });

    it('renders the other pages', () => {
        const response = proxy(request('/assets/abc/_'));

        expect(response.headers.get('Location')).toBeNull();
        expect(response.headers.get('x-middleware-next')).toBe('1');
    });
});
