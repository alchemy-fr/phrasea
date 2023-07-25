import {oauthClient} from "./oauth";
import {getUniqueFileId, uploadStateStorage} from "./uploadStateStorage";
import {apiClient} from "./lib/api";

const fileChunkSize = 5242880 // 5242880 is the minimum allowed by AWS S3;

async function asyncRequest(file, method, uri, auth, postData, onProgress, options = {}) {
    const config = {
        url: uri,
        method,
    };
    if (postData) {
        config.data = postData;
    }

    if (onProgress) {
        config.onUploadProgress = onProgress;
        /*{
            function (axiosProgressEvent) {
          loaded: number;
          total?: number;
          progress?: number; // in range [0..1]
          bytes: number; // how many bytes have been transferred since the last trigger (delta)
          estimated?: number; // estimated time in seconds
          rate?: number; // upload speed in bytes
          upload: true; // upload sign
    }*/
    }

    if (file) {
        file.abortController = new AbortController();
        config.signal = file.abortController.signal;
    }

    if (auth) {
        return oauthClient.wrapPromiseWithValidToken(({access_token, token_type}) => {
            config.headers ??= {};
            config.headers['Authorization'] = `${token_type} ${access_token}`;

            return apiClient.request(config);
        });
    } else {
        return apiClient.request(config);
    }
}

export async function uploadMultipartFile(targetId, userId, file, onProgress) {
    const fileUID = getUniqueFileId(file.file, fileChunkSize);

    try {
        const resumableUpload = uploadStateStorage.getUpload(userId, fileUID);
        const uploadParts = [];

        let resumeChunkIndex = 1,
            uploadId,
            path;

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
            const res = await asyncRequest(file, 'POST', `/uploads`, true, {
                filename: file.file.name,
                type: file.file.type,
                size: file.file.size,
            });
            console.log('res', res);
            uploadId = res.data.id;
            path = res.data.path;
            uploadStateStorage.initUpload(userId, fileUID, uploadId, path);
        }

        const fileSize = file.file.size;
        const numChunks = Math.floor(fileSize / fileChunkSize) + 1;

        for (let index = resumeChunkIndex; index < numChunks + 1; index++) {
            const start = (index - 1) * fileChunkSize;
            const end = (index) * fileChunkSize;

            const getUploadUrlResp = await asyncRequest(file, 'POST', `/uploads/${uploadId}/part`, true, {
                part: index,
            });
            console.log('getUploadUrlResp', getUploadUrlResp);

            const {url} = getUploadUrlResp.data;

            const blob = (index < numChunks) ? file.file.slice(start, end) : file.file.slice(start);

            const uploadResp = await asyncRequest(file, 'PUT', url, null, blob, (e) => {
                const multiPartEvent = {
                    ...e,
                    loaded: e.loaded + start,
                };

                onProgress(multiPartEvent);
            });

            const eTag = uploadResp.headers.etag;
            uploadParts.push({
                ETag: eTag,
                PartNumber: index,
            });

            uploadStateStorage.updateUpload(userId, fileUID, eTag);
        }

        const finalRes = await asyncRequest(file, 'POST', `/assets`, true, {
            targetId,
            multipart: {
                uploadId,
                parts: uploadParts,
            }
        });

        uploadStateStorage.removeUpload(userId, fileUID);

        return finalRes;
    } catch (e) {
        uploadStateStorage.removeUpload(userId, fileUID);
        throw e;
    }
}
