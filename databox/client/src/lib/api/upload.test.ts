import {beforeEach, describe, expect, it, vi} from 'vitest';

const post = vi.fn();

vi.mock('./http', () => ({api: {post: (...args: unknown[]) => post(...args)}}));
vi.mock('@/lib/config/ConfigProvider', () => ({
    getConfig: () => ({upload: {maxFileSize: 1000, allowedTypes: {}}}),
}));

import {multipartUpload} from './upload';

const CHUNK = 10;

function urls(from: number, partCount: number, version: string) {
    const out: Record<string, string> = {};
    for (let n = from; n <= partCount; n++) {
        out[n] = `https://s3/part-${n}?v=${version}`;
    }

    return out;
}

/** Answers every PUT with its ETag, or with `statusFor(url)` when given */
function stubXhr(statusFor: (url: string) => number = () => 200) {
    const puts: {url: string; size: number}[] = [];

    class FakeXhr {
        url = '';
        status = 0;
        upload: {onprogress?: (e: {loaded: number}) => void} = {};
        onload?: () => void;
        onerror?: () => void;
        onabort?: () => void;
        open(_method: string, url: string) {
            this.url = url;
        }
        getResponseHeader(name: string) {
            return name === 'ETag' ? `"${this.url.split('?')[0]}"` : null;
        }
        abort() {
            this.onabort?.();
        }
        send(blob: Blob) {
            puts.push({url: this.url, size: blob.size});
            this.status = statusFor(this.url);
            setTimeout(() => {
                this.upload.onprogress?.({loaded: blob.size});
                this.onload?.();
            });
        }
    }
    vi.stubGlobal('XMLHttpRequest', FakeXhr);

    return puts;
}

describe('multipartUpload (Next.js transport on the shared engine)', () => {
    beforeEach(() => {
        post.mockReset();
        post.mockImplementation(async (path: string, body: any) => {
            if (path === '/uploads') {
                return {id: 'up-1', chunkSize: CHUNK, urls: urls(1, 3, 'v1')};
            }

            return {
                chunkSize: CHUNK,
                partCount: 3,
                urls: urls(body.from, 3, 'v2'),
            };
        });
    });

    it('creates the upload once and PUTs every part', async () => {
        const puts = stubXhr();
        const progress: number[] = [];

        const result = await multipartUpload(
            new File([new Uint8Array(25)], 'a.jpg', {type: 'image/jpeg'}),
            {onProgress: p => progress.push(p.loaded)}
        );

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][1]).toEqual({
            filename: 'a.jpg',
            type: 'image/jpeg',
            size: 25,
        });
        expect(puts.map(p => p.size).sort()).toEqual([10, 10, 5]);
        expect(result).toEqual({
            uploadId: 'up-1',
            parts: [1, 2, 3].map(n => ({
                ETag: `"https://s3/part-${n}"`,
                PartNumber: n,
            })),
        });
        expect(progress.at(-1)).toBe(25);
    });

    it('renews expired URLs (403) and retries the part', async () => {
        const puts = stubXhr(url =>
            url === 'https://s3/part-2?v=v1' ? 403 : 200
        );

        const result = await multipartUpload(
            new File([new Uint8Array(25)], 'a.jpg', {type: 'image/jpeg'}),
            {concurrency: 1}
        );

        expect(result.parts.map(p => p.PartNumber)).toEqual([1, 2, 3]);
        expect(post.mock.calls[1]).toEqual([
            '/uploads/up-1/parts',
            {from: 2},
            expect.anything(),
        ]);
        expect(puts.map(p => p.url)).toEqual([
            'https://s3/part-1?v=v1',
            'https://s3/part-2?v=v1',
            'https://s3/part-2?v=v2',
            'https://s3/part-3?v=v2',
        ]);
    });

    it('rejects files above the configured maximum size before calling the API', async () => {
        stubXhr();

        await expect(
            multipartUpload(new File([new Uint8Array(1001)], 'a.jpg'))
        ).rejects.toThrow('maximum allowed size');
        expect(post).not.toHaveBeenCalled();
    });
});
