import {describe, expect, it, vi} from 'vitest';
import axios, {AxiosError} from 'axios';
import {
    getPendingPartNumbers,
    getResumeFrom,
    UploadPart,
} from './multiPartUpload';
import {axiosMultipartUpload as multipartUpload} from './axiosMultipartUpload';
import {HttpClient} from './types';

const CHUNK = 10;

function createFile(size: number): File {
    return new File([new Uint8Array(size)], 'file.bin', {
        type: 'application/octet-stream',
    });
}

function urlsFrom(from: number, partCount: number): Record<string, string> {
    const urls: Record<string, string> = {};
    for (let n = from; n <= partCount; n++) {
        urls[n] = `https://s3/part-${n}?sig=${Math.random()}`;
    }

    return urls;
}

type FakeClient = {
    client: HttpClient;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
};

function createClient(
    size: number,
    {
        failPut,
    }: {
        failPut?: (url: string, callIndex: number) => Error | undefined;
    } = {}
): FakeClient {
    const partCount = Math.max(1, Math.ceil(size / CHUNK));
    let putCalls = 0;

    const post = vi.fn(async (path: string, body: any) => {
        if (path === '/uploads') {
            return {
                data: {
                    id: 'upload-1',
                    chunkSize: CHUNK,
                    urls: urlsFrom(1, partCount),
                },
            };
        }
        if (path === '/uploads/upload-1/parts') {
            return {
                data: {
                    chunkSize: CHUNK,
                    partCount,
                    urls: urlsFrom(body.from, partCount),
                },
            };
        }
        throw new Error(`Unexpected POST ${path}`);
    });

    const put = vi.fn(async (url: string, blob: Blob, config: any) => {
        const error = failPut?.(url, putCalls++);
        if (error) {
            throw error;
        }
        config.onUploadProgress?.({loaded: blob.size, total: blob.size});
        const n = url.match(/part-(\d+)/)![1];

        return {headers: {etag: `"etag-${n}"`}};
    });

    return {client: {post, put} as unknown as HttpClient, post, put};
}

function forbidden(): AxiosError {
    return new AxiosError(
        'Forbidden',
        'ERR_BAD_REQUEST',
        undefined,
        undefined,
        {
            status: 403,
        } as any
    );
}

describe('getResumeFrom / getPendingPartNumbers', () => {
    it('resumes after the contiguous run of uploaded parts', () => {
        const parts: UploadPart[] = [
            {ETag: 'a', PartNumber: 1},
            {ETag: 'b', PartNumber: 2},
            {ETag: 'd', PartNumber: 4},
        ];
        expect(getResumeFrom(parts)).toBe(3);
        expect(getResumeFrom([])).toBe(1);
        expect(getPendingPartNumbers(5, parts)).toEqual([3, 5]);
        expect(getPendingPartNumbers(1, [])).toEqual([1]);
    });
});

describe('multipartUpload', () => {
    it('uploads every part with the URLs returned at creation', async () => {
        const size = 45;
        const {client, post, put} = createClient(size);
        const onUploadInit = vi.fn();
        const onPartUploaded = vi.fn();
        const progress: number[] = [];

        const result = await multipartUpload(client, createFile(size), {
            onUploadInit,
            onPartUploaded,
            onProgress: e => progress.push(e.loaded),
        });

        expect(result.uploadId).toBe('upload-1');
        expect(result.parts).toEqual(
            [1, 2, 3, 4, 5].map(n => ({
                ETag: `"etag-${n}"`,
                PartNumber: n,
            }))
        );
        // A single API call: no per-part URL request
        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/uploads');
        expect(post.mock.calls[0][1]).toEqual({
            filename: 'file.bin',
            type: 'application/octet-stream',
            size,
        });
        expect(put).toHaveBeenCalledTimes(5);
        // Last part is the remainder
        const sizes = put.mock.calls
            .map(([, blob]) => (blob as Blob).size)
            .sort((a, b) => a - b);
        expect(sizes).toEqual([5, 10, 10, 10, 10]);
        expect(onUploadInit).toHaveBeenCalledWith({uploadId: 'upload-1'});
        expect(onPartUploaded).toHaveBeenCalledTimes(5);
        expect(progress[progress.length - 1]).toBe(size);
    });

    it('uploads an empty file as a single empty part', async () => {
        const {client, put} = createClient(0);

        const result = await multipartUpload(client, createFile(0));

        expect(result.parts).toEqual([{ETag: '"etag-1"', PartNumber: 1}]);
        expect(put).toHaveBeenCalledTimes(1);
        expect((put.mock.calls[0][1] as Blob).size).toBe(0);
    });

    it('limits the number of parallel PUTs', async () => {
        const size = 100;
        let inFlight = 0;
        let maxInFlight = 0;
        const {client} = createClient(size);
        const realPut = client.put;
        (client as any).put = async (...args: any[]) => {
            inFlight++;
            maxInFlight = Math.max(maxInFlight, inFlight);
            await new Promise(r => setTimeout(r, 1));
            try {
                return await (realPut as any)(...args);
            } finally {
                inFlight--;
            }
        };

        await multipartUpload(client, createFile(size), {concurrency: 2});

        expect(maxInFlight).toBe(2);
    });

    it('resumes an upload by asking the URLs of the remaining parts only', async () => {
        const size = 45;
        const {client, post, put} = createClient(size);
        const uploaded: UploadPart[] = [
            {ETag: '"etag-1"', PartNumber: 1},
            {ETag: '"etag-2"', PartNumber: 2},
            {ETag: '"etag-4"', PartNumber: 4},
        ];

        const result = await multipartUpload(client, createFile(size), {
            uploadId: 'upload-1',
            uploadParts: uploaded,
        });

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/uploads/upload-1/parts');
        expect(post.mock.calls[0][1]).toEqual({from: 3});
        expect(put).toHaveBeenCalledTimes(2);
        expect(result.parts.map(p => p.PartNumber)).toEqual([1, 2, 3, 4, 5]);
    });

    it('refreshes the URLs and retries when a presigned URL expired', async () => {
        const size = 30;
        const {client, post, put} = createClient(size, {
            failPut: (url, callIndex) =>
                callIndex === 0 && url.includes('part-1')
                    ? forbidden()
                    : undefined,
        });

        const result = await multipartUpload(client, createFile(size), {
            concurrency: 1,
        });

        expect(result.parts.map(p => p.PartNumber)).toEqual([1, 2, 3]);
        expect(put).toHaveBeenCalledTimes(4);
        const refresh = post.mock.calls.find(
            ([path]) => path === '/uploads/upload-1/parts'
        );
        expect(refresh?.[1]).toEqual({from: 1});
        // The retried PUT used the fresh URL
        expect(put.mock.calls[0][0]).not.toBe(put.mock.calls[1][0]);
    });

    it('fails the whole upload and aborts on a non-recoverable part error', async () => {
        const size = 50;
        let controller: AbortController | undefined;
        const {client} = createClient(size, {
            failPut: url =>
                url.includes('part-2') ? new Error('boom') : undefined,
        });

        await expect(
            multipartUpload(client, createFile(size), {
                concurrency: 1,
                receiveAbortController: c => (controller = c),
            })
        ).rejects.toThrow('boom');
        expect(controller?.signal.aborted).toBe(true);
    });
    it('surfaces an abort from the caller as an axios cancellation', async () => {
        const size = 50;
        let controller: AbortController | undefined;
        const {client} = createClient(size, {
            failPut: (_url, callIndex) => {
                if (callIndex === 0) {
                    controller!.abort();
                }

                return undefined;
            },
        });

        const error = await multipartUpload(client, createFile(size), {
            concurrency: 1,
            receiveAbortController: c => (controller = c),
        }).catch(e => e);

        expect(axios.isCancel(error)).toBe(true);
    });
});
