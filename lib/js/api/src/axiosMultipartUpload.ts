import axios, {AxiosResponse} from 'axios';
import {getMIMETypeFromFile} from '@alchemy/core';
import {HttpClient} from './types';
import {
    MultipartUpload,
    multipartUpload,
    MultipartUploadInit,
    MultipartUploadOptions,
    MultipartUploadPlan,
    MultipartUploadTransport,
} from './multiPartUpload';

export type OnRetry = (retryCount: number, retryDelay: number) => void;

export type AxiosMultipartUploadOptions = MultipartUploadOptions & {
    uploadPath?: string;
    /** Called when fetching part URLs is retried (flaky network) */
    onRetry?: OnRetry;
};

export function createAxiosMultipartTransport(
    apiClient: HttpClient,
    {
        uploadPath = '/uploads',
        onRetry,
    }: Pick<AxiosMultipartUploadOptions, 'uploadPath' | 'onRetry'> = {}
): MultipartUploadTransport {
    const retryConfig = {
        retries: 10,
        onRetry: onRetry
            ? (retryCount: number, error: any) => {
                  onRetry(
                      retryCount,
                      error.config?.['axios-retry']?.retryDelay?.(
                          retryCount,
                          error
                      ) ?? 0
                  );
              }
            : undefined,
    };

    return {
        createUpload: async (input, signal) =>
            (
                await apiClient.post<MultipartUploadInit>(uploadPath, input, {
                    signal,
                })
            ).data,
        getPartUrls: async (uploadId, from, signal) =>
            (
                await apiClient.post<MultipartUploadPlan>(
                    `${uploadPath}/${uploadId}/parts`,
                    {from},
                    {signal, 'axios-retry': retryConfig}
                )
            ).data,
        putPart: async (url, blob, {signal, onProgress}) => {
            const res: AxiosResponse = await apiClient.put(url, blob, {
                signal,
                anonymous: true,
                onUploadProgress: e => onProgress(e.loaded),
            });
            const etag = (res.headers as {etag?: string}).etag;
            if (!etag) {
                throw new Error(
                    'ETag header is missing in the upload response. Are CORS configured correctly on the server?'
                );
            }

            return etag;
        },
        isExpiredUrlError: e =>
            axios.isAxiosError(e) && e.response?.status === 403,
        // Callers tell a cancelled upload apart with axios.isCancel()
        createAbortError: () => new axios.CanceledError('Upload aborted'),
    };
}

export function axiosMultipartUpload(
    apiClient: HttpClient,
    file: File,
    {uploadPath, onRetry, type, ...options}: AxiosMultipartUploadOptions = {}
): Promise<MultipartUpload> {
    return multipartUpload(
        createAxiosMultipartTransport(apiClient, {uploadPath, onRetry}),
        file,
        {
            ...options,
            type: type ?? getMIMETypeFromFile(file),
        }
    );
}
