import {getUniqueFileId, uploadStateStorage} from './uploadStateStorage';
import apiClient from './lib/apiClient';
import {multipartUpload} from '@alchemy/api/src/multiPartUpload';

const fileChunkSize = 5242880; // 5242880 is the minimum allowed by AWS S3;

export async function uploadMultipartFile(targetId, userId, file, onProgress) {
    const fileUID = getUniqueFileId(file.file, fileChunkSize);
    const resumableUpload = uploadStateStorage.getUpload(userId, fileUID);
    const uploadParts = [];

    let uploadId;

    if (resumableUpload) {
        uploadId = resumableUpload.u;
        for (let i = 0; i < resumableUpload.c.length; i++) {
            uploadParts.push({
                ETag: resumableUpload.c[i],
                PartNumber: i + 1,
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
        onUploadInit: ({
                           uploadId,
                       }) => {
            uploadStateStorage.initUpload(userId, fileUID, uploadId);
        },
        onPartUploaded: ({
                             etag,
                         }) => {
            uploadStateStorage.updateUpload(userId, fileUID, etag);
        },
    });

    const abortController = new AbortController();

    const finalRes = await apiClient.post(
        `/assets`,
        {
            targetId,
            multipart,
        },
        {
            signal: abortController.signal,
        }
    );

    uploadStateStorage.removeUpload(userId, fileUID);

    return finalRes;
}
