import {getUniqueFileId, uploadStateStorage} from './uploadStateStorage';
import apiClient from './lib/apiClient';

const fileChunkSize = 5242880; // 5242880 is the minimum allowed by AWS S3;

export async function uploadMultipartFile(targetId, userId, file, onProgress) {
    const fileUID = getUniqueFileId(file.file, fileChunkSize);

    const resumableUpload = uploadStateStorage.getUpload(userId, fileUID);
    const uploadParts = [];

    let resumeChunkIndex = 1,
        uploadId;

    if (resumableUpload) {
        uploadId = resumableUpload.u;
        resumeChunkIndex = resumableUpload.c.length + 1;
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

    const fileSize = file.file.size;
    const numChunks = Math.floor(fileSize / fileChunkSize) + 1;

    for (let index = resumeChunkIndex; index < numChunks + 1; index++) {
        const start = (index - 1) * fileChunkSize;
        const end = index * fileChunkSize;

        file.abortController = new AbortController();

        const getUploadUrlResp = await apiClient.post(
            `/uploads/${uploadId}/part`,
            {
                part: index,
            },
            {
                signal: file.abortController.signal,
            }
        );

        const {url} = getUploadUrlResp.data;

        const blob =
            index < numChunks
                ? file.file.slice(start, end)
                : file.file.slice(start);

        file.abortController = new AbortController();

        const uploadResp = await apiClient.put(url, blob, {
            signal: file.abortController.signal,
            anonymous: true,
            onUploadProgress: e => {
                const multiPartEvent = {
                    ...e,
                    loaded: e.loaded + start,
                };

                onProgress(multiPartEvent);
            },
        });

        const eTag = uploadResp.headers.etag;
        uploadParts.push({
            ETag: eTag,
            PartNumber: index,
        });

        uploadStateStorage.updateUpload(userId, fileUID, eTag);
    }

    file.abortController = new AbortController();

    const finalRes = await apiClient.post(
        `/assets`,
        {
            targetId,
            multipart: {
                uploadId,
                parts: uploadParts,
            },
        },
        {
            signal: file.abortController.signal,
        }
    );

    uploadStateStorage.removeUpload(userId, fileUID);

    return finalRes;
}
