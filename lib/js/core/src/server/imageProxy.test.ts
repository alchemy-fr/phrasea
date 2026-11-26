// @vitest-environment node
import {describe, expect, it, vi} from 'vitest';
import {IMAGE_PROXY_MAX_BYTES, proxyImage} from './imageProxy';

function answer(
    status: number,
    contentType: string | undefined,
    body = Buffer.from('PNG')
) {
    return vi.fn().mockResolvedValue({
        status,
        headers: contentType ? {'content-type': contentType} : {},
        body,
    });
}

describe('proxyImage', () => {
    it('serves the image with caching headers', async () => {
        const get = answer(200, 'image/png; charset=binary');
        const res = await proxyImage('https://s3/thumb.png?sig=1', {
            verifySsl: false,
            get,
        });
        expect(res.status).toBe(200);
        expect(res.headers.get('Content-Type')).toBe('image/png');
        expect(res.headers.get('Cache-Control')).toBe('public, max-age=3600');
        expect(res.headers.get('X-Robots-Tag')).toBe('noindex');
        expect(Buffer.from(await res.arrayBuffer()).toString()).toBe('PNG');
        expect(get).toHaveBeenCalledWith('https://s3/thumb.png?sig=1', {
            headers: {Accept: 'image/*'},
            rejectUnauthorized: false,
            timeout: 5000,
            maxBytes: IMAGE_PROXY_MAX_BYTES,
        });
    });

    it('answers 404 to anything but an image', async () => {
        for (const get of [
            answer(200, 'text/html'),
            answer(200, undefined),
            answer(200, 'image/svg+xml'),
            answer(403, 'image/png'),
            vi.fn().mockRejectedValue(new Error('timeout')),
        ]) {
            const res = await proxyImage('https://s3/x', {
                verifySsl: true,
                get,
            });
            expect(res.status).toBe(404);
            expect(res.headers.get('X-Robots-Tag')).toBe('noindex');
        }
    });
});
