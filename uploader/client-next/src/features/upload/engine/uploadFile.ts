import {isApiError} from '@/lib/api/http';
import {createAsset} from '@/lib/api/uploader';
import {
    uploadFileParts,
    type MultipartUpload,
    type RetryNotice,
} from '@/lib/api/upload';
import type {UploadFileFn} from './UploadQueue';
import {getFileKey, ResumeStorage} from './resumeStorage';

export type UploadFileDeps = {
    targetId: string;
    resumeStorage: ResumeStorage;
    /** A renewal of the part URLs of `file` is being retried (null: done) */
    onRetry?: (file: File, notice: RetryNotice | null) => void;
    upload?: typeof uploadFileParts;
    complete?: typeof createAsset;
};

/**
 * Uploads a file of the batch to the storage and turns it into an uploader
 * asset. An upload of the same file interrupted earlier is resumed from its
 * first missing part; when the storage no longer knows it, the file starts
 * over.
 */
export function createUploadFile({
    targetId,
    resumeStorage,
    onRetry,
    upload = uploadFileParts,
    complete = createAsset,
}: UploadFileDeps): UploadFileFn {
    return async (file, {signal, onProgress}) => {
        const key = getFileKey(file);

        const send = (resume?: MultipartUpload): Promise<MultipartUpload> => {
            let uploadId = resume?.uploadId;

            return upload(file, {
                signal,
                onProgress,
                resume,
                onUploadInit: id => {
                    uploadId = id;
                    resumeStorage.start(key, id);
                },
                onPartUploaded: part => {
                    if (uploadId) {
                        resumeStorage.addPart(key, uploadId, part);
                    }
                },
                onRetry: onRetry ? notice => onRetry(file, notice) : undefined,
            });
        };

        const stored = resumeStorage.get(key);
        let multipart: MultipartUpload;
        if (stored) {
            try {
                multipart = await send({
                    uploadId: stored.uploadId,
                    parts: stored.parts,
                });
            } catch (e) {
                // The upload expired or was completed meanwhile
                if (!isApiError(e) || e.status >= 500) {
                    throw e;
                }
                resumeStorage.clear(key);
                multipart = await send();
            }
        } else {
            multipart = await send();
        }

        const asset = await complete(targetId, multipart, signal);
        resumeStorage.clear(key);

        return {assetId: asset.id};
    };
}
