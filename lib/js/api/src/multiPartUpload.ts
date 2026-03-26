import {AxiosProgressEvent} from 'axios';
import {HttpClient, MultipartUpload, UploadPart} from './types';

export type OnRetry = (retryCount: number, retryDelay: number) => void;

export type MultipartUploadOptions = {
    uploadId?: string;
    uploadParts?: UploadPart[];
    uploadPath?: string;
    onUploadInit?: (props: {uploadId: string}) => void;
    onPartUploaded?: (props: {
        uploadId: string;
        etag: string;
        partNumber: number;
    }) => void;
    onProgress?: (event: AxiosProgressEvent) => void;
    onRetry?: OnRetry;
    receiveAbortController?: (abortController: AbortController) => void;
    minChunkSize?: Readonly<number>;
    maxChunkSize?: Readonly<number>;
    maxPartNumber?: Readonly<number>;
    maxFileSize?: Readonly<number>;
};

export async function multipartUpload(
    apiClient: HttpClient,
    file: File,
    {
        uploadParts: initialUploadParts,
        uploadId: initialUploadId,
        uploadPath = '/uploads',
        onUploadInit,
        onPartUploaded,
        onProgress,
        receiveAbortController,
        minChunkSize = 5242880,
        maxChunkSize,
        maxPartNumber = 10000,
        maxFileSize,
        onRetry,
    }: MultipartUploadOptions = {}
): Promise<MultipartUpload> {
    const parts: UploadPart[] = initialUploadParts ?? [];

    // eslint-disable-next-line no-console
    console.debug('multipartUpload', {
        fileSize: file.size,
        minChunkSize,
        maxChunkSize,
        maxPartNumber,
        maxFileSize,
    });

    if (maxFileSize && file.size > maxFileSize) {
        throw new Error(
            `File size exceeds the maximum allowed size of ${maxFileSize} bytes`
        );
    }
    const calculatedMinChunkSize = Math.max(
        minChunkSize,
        Math.ceil(file.size / maxPartNumber)
    );
    if (maxChunkSize && calculatedMinChunkSize > maxChunkSize) {
        throw new Error(
            `Minimum chunk size of ${calculatedMinChunkSize} bytes exceeds the maximum allowed chunk size of ${maxChunkSize} bytes for a file of size ${file.size} bytes with a maximum of ${maxPartNumber} parts`
        );
    }

    const chunkSize = Math.min(
        maxChunkSize ?? calculatedMinChunkSize,
        calculatedMinChunkSize
    );
    if (chunkSize < minChunkSize) {
        throw new Error(
            `Calculated chunk size ${chunkSize} bytes is less than the minimum allowed chunk size of ${minChunkSize} bytes`
        );
    }

    // eslint-disable-next-line no-console
    console.debug(`Starting upload with chunks of size ${chunkSize} bytes`);

    let uploadId: string | undefined = initialUploadId;

    if (parts.length === 0) {
        const abortControllerInit = new AbortController();
        receiveAbortController?.(abortControllerInit);

        const res = await apiClient.post(
            uploadPath,
            {
                filename: file.name,
                type: file.type,
                size: file.size,
            },
            {
                signal: abortControllerInit.signal,
            }
        );
        uploadId = res.data.id;
        onUploadInit?.({uploadId: uploadId!});
    } else if (!uploadId) {
        throw new Error('uploadId is required when uploadParts are provided');
    }

    const numChunks = Math.floor(file.size / chunkSize) + 1;
    const startIndex = parts.length + 1;

    for (let index = startIndex; index < numChunks + 1; index++) {
        const start = (index - 1) * chunkSize;
        const end = index * chunkSize;

        const abortControllerLoop = new AbortController();
        receiveAbortController?.(abortControllerLoop);

        const getUploadUrlResp = await apiClient.post(
            `/uploads/${uploadId}/part`,
            {
                part: index,
            },
            {
                'signal': abortControllerLoop.signal,
                'axios-retry': {
                    retries: 10,
                    onRetry: onRetry
                        ? (retryCount, error) => {
                              onRetry(
                                  retryCount,
                                  error.config?.['axios-retry']?.retryDelay?.(
                                      retryCount,
                                      error
                                  ) ?? 0
                              );
                          }
                        : undefined,
                },
            }
        );

        const {url} = getUploadUrlResp.data;

        const blob =
            index < numChunks ? file.slice(start, end) : file.slice(start);

        const abortControllerPut = new AbortController();
        receiveAbortController?.(abortControllerPut);

        const uploadResp = await apiClient.put(url, blob, {
            signal: abortControllerPut.signal,
            anonymous: true,
            onUploadProgress: (e: AxiosProgressEvent) => {
                const multiPartEvent: AxiosProgressEvent = {
                    ...e,
                    loaded: e.loaded + start,
                };

                onProgress?.(multiPartEvent);
            },
        });

        const etag = (
            uploadResp.headers as {
                etag: string;
            }
        ).etag;
        if (!etag) {
            throw new Error(
                'ETag header is missing in the upload response. Are CORS configured correctly on the server?'
            );
        }

        parts.push({
            ETag: etag,
            PartNumber: index,
        });

        onPartUploaded?.({
            uploadId: uploadId!,
            etag,
            partNumber: index,
        });
    }

    return {
        uploadId: uploadId!,
        parts,
    };
}
