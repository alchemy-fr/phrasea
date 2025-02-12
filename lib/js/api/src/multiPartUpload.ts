import {HttpClient} from "./httpClient";
import {AxiosProgressEvent} from "axios";

export type MultipartUpload = {
    uploadId: string;
    parts: UploadPart[];
};

export type UploadPart = {
    ETag: string;
    PartNumber: number;
}

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
    receiveAbortController?: (abortController: AbortController) => void;
    fileChunkSize?: number;
}

const minChunkSize = 5242880; // 5242880 is the minimum allowed by AWS S3

export async function multipartUpload(apiClient: HttpClient, file: File, {
    uploadParts: initialUploadParts,
    uploadId: initialUploadId,
    uploadPath = '/uploads',
    onUploadInit,
    onPartUploaded,
    onProgress,
    receiveAbortController,
    fileChunkSize = minChunkSize,
}: MultipartUploadOptions = {}): Promise<MultipartUpload> {
    const parts: UploadPart[] = initialUploadParts ?? [];

    if (fileChunkSize < minChunkSize) {
        throw new Error(`fileChunkSize must be at least ${minChunkSize}`);
    }

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

    const numChunks = Math.floor(file.size / fileChunkSize) + 1;
    const startIndex = parts.length + 1;

    for (let index = startIndex; index < numChunks + 1; index++) {
        const start = (index - 1) * fileChunkSize;
        const end = index * fileChunkSize;

        const abortControllerLoop = new AbortController();
        receiveAbortController?.(abortControllerLoop);

        const getUploadUrlResp = await apiClient.post(
            `/uploads/${uploadId}/part`,
            {
                part: index,
            },
            {
                signal: abortControllerLoop.signal,
            }
        );

        const {url} = getUploadUrlResp.data;

        const blob =
            index < numChunks
                ? file.slice(start, end)
                : file.slice(start);

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

        const etag = (uploadResp.headers as {
            etag: string;
        }).etag;

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
