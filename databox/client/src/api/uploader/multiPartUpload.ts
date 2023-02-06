import {getUniqueFileId, uploadStateStorage} from "../../lib/upload/uploadStateStorage";
import {makeAuthorizationHeaders} from "./file";
import uploaderClient from "../uploader-client";
import {AxiosProgressEvent} from "axios";

type OnProgress = (progressEvent: AxiosProgressEvent) => void;

export type UploadedFile = {
    data?: Record<string, any>;
    file: File,
}

export async function uploadMultipartFile(
    targetSlug: string,
    userId: string,
    accessToken: string,
    upload: UploadedFile,
    onProgress?: OnProgress
): Promise<string> {
    const fileChunkSize = 5242880; // 5242880 is the minimum allowed by AWS S3
    const file = upload.file;
    const fileUID = getUniqueFileId(file, fileChunkSize);

    try {
        const resumableUpload = uploadStateStorage.getUpload(userId, fileUID);
        const uploadParts = [];

        let resumeChunkIndex = 1,
            uploadId: string;

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
            const {data: res} = await uploaderClient.post(`/uploads`, {
                filename: file.name,
                type: file.type,
                size: file.size,
            }, {
                headers: makeAuthorizationHeaders(accessToken),
            });

            uploadId = res.id;
            const path = res.path;
            uploadStateStorage.initUpload(userId, fileUID, uploadId, path);
        }

        const fileSize = file.size;
        const numChunks = Math.floor(fileSize / fileChunkSize) + 1;

        for (let index = resumeChunkIndex; index < numChunks + 1; index++) {
            const start = (index - 1) * fileChunkSize;
            const end = (index) * fileChunkSize;

            const {data: getUploadUrlResp} = await uploaderClient.post(`/uploads/${uploadId}/part`, {
                part: index,
            }, {
                headers: makeAuthorizationHeaders(accessToken),
            });

            const blob = (index < numChunks) ? file.slice(start, end) : file.slice(start);

            const uploadResp = await uploaderClient.put(getUploadUrlResp.url, blob, {
                onUploadProgress: (e: AxiosProgressEvent) => {
                    const multiPartEvent = {
                        ...e,
                        loaded: e.loaded + start,
                    };

                    onProgress && onProgress(multiPartEvent);
                }
            });

            const eTag = uploadResp.headers.etag as string;
            uploadParts.push({
                ETag: eTag,
                PartNumber: index,
            });

            uploadStateStorage.updateUpload(userId, fileUID, eTag);
        }

        const res = await uploaderClient.post(`/assets`, {
            targetSlug,
            multipart: {
                uploadId,
                parts: uploadParts,
            },
            data: upload.data,
        }, {
            headers: makeAuthorizationHeaders(accessToken),
        });

        uploadStateStorage.removeUpload(userId, fileUID);

        return res.data.id;
    } catch (e) {
        uploadStateStorage.removeUpload(userId, fileUID);
        throw e;
    }
}
