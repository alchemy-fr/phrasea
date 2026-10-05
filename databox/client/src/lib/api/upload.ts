import {
    multipartUpload as runMultipartUpload,
    type MultipartUpload,
    type MultipartUploadInit,
    type MultipartUploadPlan,
    type MultipartUploadTransport,
    type UploadPart,
} from '@alchemy/api';
import {api} from './http';
import {getConfig} from '@/lib/config/ConfigProvider';
import {getMimeTypeFromFile} from '@/lib/utils/mime';

export type {MultipartUpload, UploadPart};

export type UploadProgress = {loaded: number; total: number};

export type MultipartUploadOptions = {
    onProgress?: (progress: UploadProgress) => void;
    signal?: AbortSignal;
    /** Number of parts PUT in parallel */
    concurrency?: number;
};

class PartUploadError extends Error {
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
 * progress API, so XHR is used here).
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

/** Plugs the shared multipart upload engine on the ky API client and XHR. */
const transport: MultipartUploadTransport = {
    createUpload: (input, signal) =>
        api.post<MultipartUploadInit>('/uploads', input, {signal}),
    getPartUrls: (uploadId, from, signal) =>
        api.post<MultipartUploadPlan>(
            `/uploads/${uploadId}/parts`,
            {from},
            {signal}
        ),
    putPart: (url, blob, {signal, onProgress}) =>
        putBlob(url, blob, onProgress, signal),
    isExpiredUrlError: e => e instanceof PartUploadError && e.status === 403,
};

export async function multipartUpload(
    file: File,
    {onProgress, signal, concurrency}: MultipartUploadOptions = {}
): Promise<MultipartUpload> {
    const {upload} = getConfig();
    if (upload.maxFileSize && file.size > upload.maxFileSize) {
        throw new Error(
            `File size exceeds the maximum allowed size of ${upload.maxFileSize} bytes`
        );
    }

    const result = await runMultipartUpload(transport, file, {
        type: getMimeTypeFromFile(file),
        signal,
        concurrency,
        onProgress: onProgress
            ? ({loaded, total}) => onProgress({loaded, total})
            : undefined,
    });
    onProgress?.({loaded: file.size, total: file.size});

    return result;
}
