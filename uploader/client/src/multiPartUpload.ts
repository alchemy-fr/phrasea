import {getUniqueFileId, uploadStateStorage} from './uploadStateStorage.ts';
import {
    axiosMultipartUpload,
    OnRetry,
} from '@alchemy/api/src/axiosMultipartUpload';
import {MultipartUploadProgress} from '@alchemy/api/src/multiPartUpload';
import {UploadPart} from '@alchemy/api';
import {AbortableFile, UploadedAsset} from './types.ts';
import {apiClient} from './init.ts';

type Props = {
    targetId: string;
    userId: string;
    file: AbortableFile;
    onRetry: OnRetry;
    onProgress: (event: MultipartUploadProgress) => void;
};

export async function uploadMultipartFile({
    targetId,
    userId,
    file,
    onRetry,
    onProgress,
}: Props): Promise<UploadedAsset> {
    const fileUID = getUniqueFileId(file.file);
    const resumableUpload = uploadStateStorage.getUpload(userId, fileUID);

    // Resume only when at least one part made it: otherwise start afresh, the
    // server decides the part size and presigns every part on creation.
    let uploadId: string | undefined;
    const uploadParts: UploadPart[] = [];
    if (
        resumableUpload &&
        resumableUpload.c.length > 0 &&
        typeof resumableUpload.c[0] === 'object'
    ) {
        uploadId = resumableUpload.u;
        for (const part of resumableUpload.c) {
            uploadParts.push({
                ETag: part.etag,
                PartNumber: part.n,
            });
        }
    }

    const multipart = await axiosMultipartUpload(apiClient, file.file, {
        uploadId,
        uploadParts,
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
