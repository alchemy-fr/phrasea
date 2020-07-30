import request from "superagent";
import config from "./config";
import {oauthClient} from "./oauth";

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

export async function uploadMultipartFile(accessToken, file, onProgress) {
    const res = await asyncRequest('post', `${config.getUploadBaseURL()}/upload/start`, accessToken, {
        filename: file.file.name,
        type: file.file.type,
    });
    const {uploadId, path} = res.json;

    const fileChunkSize = 5242880 // Minimum allowed by AWS S3;
    const fileSize = file.file.size;
    const numChunks = Math.floor(fileSize / fileChunkSize) + 1;
    const uploadParts = [];

    for (let index = 1; index < numChunks + 1; index++) {
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

        uploadParts.push({
            ETag: uploadResp.res.headers.etag,
            PartNumber: index,
        })
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

    return finalRes;
}
