import {api} from './http';
import {getConfig} from '@/lib/config/ConfigProvider';
import {getMimeTypeFromFile} from '@/lib/utils/mime';

export type UploadPart = {ETag: string; PartNumber: number};
export type MultipartUpload = {uploadId: string; parts: UploadPart[]};

export type UploadProgress = {loaded: number; total: number};

export type MultipartUploadOptions = {
    onProgress?: (progress: UploadProgress) => void;
    signal?: AbortSignal;
};

function resolveChunkSize(size: number): number {
    const {upload} = getConfig();
    const minChunkSize = upload.minChunkSize ?? 5 * 1024 * 1024;
    const maxPartNumber = upload.maxPartNumber ?? 10000;

    if (upload.maxFileSize && size > upload.maxFileSize) {
        throw new Error(
            `File size exceeds the maximum allowed size of ${upload.maxFileSize} bytes`
        );
    }
    const chunk = Math.max(minChunkSize, Math.ceil(size / maxPartNumber));
    if (upload.maxChunkSize && chunk > upload.maxChunkSize) {
        throw new Error('File is too large to be uploaded in parts');
    }

    return chunk;
}

/**
 * PUT a blob to a presigned URL with progress reporting (fetch has no upload
 * progress API, so XHR is used here).
 */
function putBlob(
    url: string,
    blob: Blob,
    onProgress?: (loaded: number) => void,
    signal?: AbortSignal
): Promise<string> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', url, true);
        xhr.upload.onprogress = e => onProgress?.(e.loaded);
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
                reject(new Error(`Upload failed with status ${xhr.status}`));
            }
        };
        signal?.addEventListener('abort', () => xhr.abort());
        xhr.send(blob);
    });
}

export async function multipartUpload(
    file: File,
    {onProgress, signal}: MultipartUploadOptions = {}
): Promise<MultipartUpload> {
    const size = file.size;
    const chunkSize = resolveChunkSize(size);
    const type = getMimeTypeFromFile(file);
    if (!type) {
        throw new Error(`Unable to determine MIME type for file: ${file.name}`);
    }

    const init = await api.post<{id: string}>(
        '/uploads',
        {filename: file.name, type, size},
        {signal}
    );
    const uploadId = init.id;
    const parts: UploadPart[] = [];
    const numChunks = Math.max(1, Math.ceil(size / chunkSize));

    for (let index = 1; index <= numChunks; index++) {
        const start = (index - 1) * chunkSize;
        const end = index * chunkSize;
        const {url} = await api.post<{url: string}>(
            `/uploads/${uploadId}/part`,
            {part: index},
            {signal}
        );
        const blob =
            index < numChunks ? file.slice(start, end) : file.slice(start);
        const etag = await putBlob(
            url,
            blob,
            loaded => onProgress?.({loaded: start + loaded, total: size}),
            signal
        );
        parts.push({ETag: etag, PartNumber: index});
    }
    onProgress?.({loaded: size, total: size});

    return {uploadId, parts};
}
