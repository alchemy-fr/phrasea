import {
    multipartUpload as runMultipartUpload,
    type MultipartUpload,
    type MultipartUploadInit,
    type MultipartUploadPlan,
    type MultipartUploadTransport,
    type UploadPart,
} from '@alchemy/api';
import {api, isApiError, type RequestOptions} from './http';
import {getMimeTypeFromFile} from '@alchemy/next-ui/utils/mime';

export type {MultipartUpload, UploadPart};

export class PartUploadError extends Error {
    constructor(
        message: string,
        public readonly status: number
    ) {
        super(message);
        this.name = 'PartUploadError';
    }
}

/**
 * PUT a blob to a presigned URL with progress reporting (fetch has no upload
 * progress API, so XHR is used here). Anonymous: the URL carries its own
 * signature.
 */
function putBlob(
    url: string,
    blob: Blob,
    onProgress: (loaded: number) => void,
    signal: AbortSignal
): Promise<string> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url, true);
        xhr.upload.onprogress = e => onProgress(e.loaded);
        xhr.onerror = () => reject(new Error('Network error during upload'));
        xhr.onabort = () => reject(new DOMException('Aborted', 'AbortError'));
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                const etag = xhr.getResponseHeader('ETag');
                if (!etag) {
                    reject(
                        new Error(
                            'ETag header is missing in the upload response. Are CORS configured correctly on the storage?'
                        )
                    );

                    return;
                }
                resolve(etag);
            } else {
                reject(
                    new PartUploadError(
                        `Upload failed with status ${xhr.status}`,
                        xhr.status
                    )
                );
            }
        };
        if (signal.aborted) {
            reject(new DOMException('Aborted', 'AbortError'));

            return;
        }
        signal.addEventListener('abort', () => xhr.abort());
        xhr.send(blob);
    });
}

export type RetryNotice = {attempt: number; delayMs: number};

export const PART_URLS_MAX_RETRIES = 10;

function sleep(ms: number, signal: AbortSignal): Promise<void> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(resolve, ms);
        signal.addEventListener('abort', () => {
            clearTimeout(timer);
            reject(new DOMException('Aborted', 'AbortError'));
        });
    });
}

/** Exponential back-off, capped at 30 s */
export function retryDelay(attempt: number): number {
    return Math.min(30_000, 1000 * 2 ** (attempt - 1));
}

/**
 * Renewing the part URLs is what an upload resumes from (expired URLs, a
 * network that came back): it is retried on network and server errors, never
 * on a client error.
 */
export async function withRetry<T>(
    run: () => Promise<T>,
    signal: AbortSignal,
    onRetry?: (notice: RetryNotice | null) => void,
    maxRetries = PART_URLS_MAX_RETRIES
): Promise<T> {
    for (let attempt = 1; ; attempt++) {
        try {
            const result = await run();
            if (attempt > 1) {
                onRetry?.(null);
            }

            return result;
        } catch (e) {
            const retryable =
                !signal.aborted &&
                attempt <= maxRetries &&
                !(isApiError(e) && e.status < 500 && e.status !== 408);
            if (!retryable) {
                if (attempt > 1) {
                    onRetry?.(null);
                }
                throw e;
            }
            const delayMs = retryDelay(attempt);
            onRetry?.({attempt, delayMs});
            await sleep(delayMs, signal);
        }
    }
}

export type FileUploadOptions = {
    signal?: AbortSignal;
    onProgress?: (loaded: number) => void;
    /** Resumes an upload started earlier */
    resume?: {uploadId: string; parts: UploadPart[]};
    onUploadInit?: (uploadId: string) => void;
    onPartUploaded?: (part: UploadPart) => void;
    /** A renewal of the part URLs is being retried (null: it went through) */
    onRetry?: (notice: RetryNotice | null) => void;
    concurrency?: number;
    /** How the API calls are authenticated (an upload link) */
    requestOptions?: RequestOptions;
};

/**
 * Sends a file straight to the storage: the API presigns every part, which
 * are PUT several at a time without authentication.
 */
export function uploadFileParts(
    file: File,
    {
        signal,
        onProgress,
        resume,
        onUploadInit,
        onPartUploaded,
        onRetry,
        concurrency,
        requestOptions,
    }: FileUploadOptions = {}
): Promise<MultipartUpload> {
    const transport: MultipartUploadTransport = {
        createUpload: (input, s) =>
            api.post<MultipartUploadInit>('/uploads', input, {
                ...requestOptions,
                signal: s,
            }),
        getPartUrls: (uploadId, from, s) =>
            withRetry(
                () =>
                    api.post<MultipartUploadPlan>(
                        `/uploads/${uploadId}/parts`,
                        {from},
                        {...requestOptions, signal: s}
                    ),
                s,
                onRetry
            ),
        putPart: (url, blob, {signal: s, onProgress: p}) =>
            putBlob(url, blob, p, s),
        isExpiredUrlError: e =>
            e instanceof PartUploadError && e.status === 403,
    };

    return runMultipartUpload(transport, file, {
        type: getMimeTypeFromFile(file),
        signal,
        concurrency,
        uploadId: resume?.uploadId,
        uploadParts: resume?.parts,
        onUploadInit: ({uploadId}) => onUploadInit?.(uploadId),
        onPartUploaded: ({etag, partNumber}) =>
            onPartUploaded?.({ETag: etag, PartNumber: partNumber}),
        onProgress: onProgress ? ({loaded}) => onProgress(loaded) : undefined,
    });
}
