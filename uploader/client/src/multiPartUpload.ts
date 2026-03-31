import {getUniqueFileId, uploadStateStorage} from './uploadStateStorage.ts';
import {
    multipartUpload,
    OnRetry,
    resolveChunkParams,
} from '@alchemy/api/src/multiPartUpload';
import {AbortableFile, UploadedAsset} from './types.ts';
import {apiClient, config} from './init.ts';
import {AxiosProgressEvent} from 'axios';

type Props = {
    targetId: string;
    userId: string;
    file: AbortableFile;
    onRetry: OnRetry;
    onProgress: (event: AxiosProgressEvent) => void;
};

export async function uploadMultipartFile({
    targetId,
    userId,
    file,
    onRetry,
    onProgress,
}: Props): Promise<UploadedAsset> {
    const {maxPartNumber, minChunkSize, maxChunkSize, maxFileSize} =
        config.upload;

    const {chunkSize} = resolveChunkParams(file.file, {
        maxFileSize,
        maxChunkSize,
        maxPartNumber,
        minChunkSize,
    });

    const fileUID = getUniqueFileId(file.file, chunkSize);
    const resumableUpload = uploadStateStorage.getUpload(userId, fileUID);
    const uploadParts = [];

    let uploadId;

    if (
        resumableUpload &&
        // Ensure new format
        resumableUpload.c.length > 0 &&
        typeof resumableUpload.c[0] === 'object'
    ) {
        uploadId = resumableUpload.u;
        for (let i = 0; i < resumableUpload.c.length; i++) {
            const part = resumableUpload.c[i];
            uploadParts.push({
                ETag: part.etag,
                PartNumber: part.n,
            });
        }
    } else {
        file.abortController = new AbortController();

        const res = await apiClient.post(
            `/uploads`,
            {
                filename: file.file.name,
                type: file.file.type,
                size: file.file.size,
            },
            {
                signal: file.abortController.signal,
            }
        );
        uploadId = res.data.id;
        uploadStateStorage.initUpload(userId, fileUID, uploadId);
    }

    const multipart = await multipartUpload(apiClient, file.file, {
        uploadParts,
        uploadId,
        onProgress,
        onRetry,
        onUploadInit: ({uploadId}) => {
            uploadStateStorage.initUpload(userId, fileUID, uploadId);
        },
        onPartUploaded: ({etag, partNumber}) => {
            uploadStateStorage.updateUpload(userId, fileUID, etag, partNumber);
        },
        receiveAbortController: abortController => {
            file.abortController = abortController;
        },
        maxPartNumber,
        minChunkSize,
        maxChunkSize,
        maxFileSize,
    });

    file.abortController = new AbortController();

    const finalRes = await apiClient.post(
        `/assets`,
        {
            targetId,
            multipart,
        },
        {
            signal: file.abortController.signal,
        }
    );

    uploadStateStorage.removeUpload(userId, fileUID);

    return finalRes.data;
}
