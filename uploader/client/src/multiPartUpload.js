import request from "superagent";
import config from "./config";
import {oauthClient} from "./oauth";
import {getUniqueFileId, uploadStateStorage} from "./uploadStateStorage";

const fileChunkSize = 5242880 // Minimum allowed by AWS S3;

async function asyncRequest(method, uri, accessToken, postData, onProgress) {
    return new Promise((resolve, reject) => {
        const req = request[method](uri)
            .accept('json');

        if (accessToken) {
            req.set('Authorization', `Bearer ${accessToken}`)
        }

        if (postData) {
            req
                .set('Content-Type', 'application/json')
                .send(postData);
        }

        if (onProgress) {
            req.on('progress', onProgress);
        }

        req
            .end((err, res) => {
                if (!oauthClient.isResponseValid(err, res)) {
                    reject(err);
                }

                if (res && res.text) {
                    const json = JSON.parse(res.text);
                    resolve({
                        res,
                        json,
                    });
                } else {
                    resolve({res});
                }
            });
    });
}

export async function uploadMultipartFile(userId, accessToken, file, onProgress) {
    const fileUID = getUniqueFileId(file.file, fileChunkSize);
    const resumableUpload = uploadStateStorage.getUpload(userId, fileUID);
    const uploadParts = [];

    let resumeChunkIndex = 1, uploadId, path;

    if (resumableUpload) {
        uploadId = resumableUpload.u;
        path = resumableUpload.p;
        resumeChunkIndex = resumableUpload.c.length + 1;
        for (let i = 0; i < resumableUpload.c.length; i++) {
            uploadParts.push({
                ETag: resumableUpload.c[i],
                PartNumber: i + 1,
            });
        }
    } else {
        const res = await asyncRequest('post', `${config.getUploadBaseURL()}/upload/start`, accessToken, {
            filename: file.file.name,
            type: file.file.type,
        });
        uploadId = res.json.uploadId;
        path = res.json.path;
        uploadStateStorage.initUpload(userId, fileUID, uploadId, path);
    }

    const fileSize = file.file.size;
    const numChunks = Math.floor(fileSize / fileChunkSize) + 1;

    for (let index = resumeChunkIndex; index < numChunks + 1; index++) {
        const start = (index - 1) * fileChunkSize;
        const end = (index) * fileChunkSize;
        const blob = (index < numChunks) ? file.file.slice(start, end) : file.file.slice(start);

        const getUploadUrlResp = await asyncRequest('post', `${config.getUploadBaseURL()}/upload/url`, accessToken, {
            filename: path,
            uploadId,
            part: index,
        });

        const {url} = getUploadUrlResp.json;

        const uploadResp = await asyncRequest('put', url, null, blob, (e) => {
            if (e.direction !== 'upload') {
                return;
            }

            const multiPartEvent = {
                ...e,
                loaded: e.loaded + start,
            };

            onProgress(multiPartEvent);
        });

        const eTag = uploadResp.res.headers.etag;
        uploadParts.push({
            ETag: eTag,
            PartNumber: index,
        });

        uploadStateStorage.updateUpload(userId, fileUID, eTag);
    }

    const {res: finalRes} = await asyncRequest('post', `${config.getUploadBaseURL()}/assets`, accessToken, {
        multipart: {
            path,
            uploadId,
            parts: uploadParts,
            size: file.file.size,
            filename: file.file.name,
            type: file.file.type,
        }
    });

    uploadStateStorage.removeUpload(userId, fileUID);

    return finalRes;
}
