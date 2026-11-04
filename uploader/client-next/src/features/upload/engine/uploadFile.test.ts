import {describe, expect, it, vi} from 'vitest';
import {ApiError} from '@/lib/api/http';
import type {FileUploadOptions} from '@/lib/api/upload';
import {createUploadFile} from './uploadFile';
import {getFileKey, ResumeStorage} from './resumeStorage';

function memoryStorage() {
    const map = new Map<string, string>();

    return {
        getItem: (k: string) => map.get(k) ?? null,
        setItem: (k: string, v: string) => void map.set(k, v),
        removeItem: (k: string) => void map.delete(k),
    };
}

const file = new File(['abcdef'], 'a.jpg', {
    type: 'image/jpeg',
    lastModified: 1,
});
const ctx = {signal: new AbortController().signal, onProgress: () => undefined};

describe('createUploadFile', () => {
    it('remembers the parts sent, and forgets them once the asset exists', async () => {
        const resumeStorage = new ResumeStorage('alice', memoryStorage());
        let seen: ReturnType<ResumeStorage['get']>;
        const upload = vi.fn(async (_f: File, o: FileUploadOptions = {}) => {
            o.onUploadInit?.('u1');
            o.onPartUploaded?.({ETag: 'e1', PartNumber: 1});
            seen = resumeStorage.get(getFileKey(file));

            return {uploadId: 'u1', parts: [{ETag: 'e1', PartNumber: 1}]};
        });
        const complete = vi.fn(async () => ({id: 'asset-1'}));

        const result = await createUploadFile({
            targetId: 't1',
            resumeStorage,
            upload,
            complete,
        })(file, ctx);

        expect(result).toEqual({assetId: 'asset-1'});
        expect(seen).toMatchObject({uploadId: 'u1', parts: [{PartNumber: 1}]});
        expect(complete).toHaveBeenCalledWith(
            't1',
            {uploadId: 'u1', parts: [{ETag: 'e1', PartNumber: 1}]},
            ctx.signal
        );
        expect(resumeStorage.get(getFileKey(file))).toBeUndefined();
    });

    it('resumes an upload interrupted earlier', async () => {
        const resumeStorage = new ResumeStorage('alice', memoryStorage());
        resumeStorage.start(getFileKey(file), 'u1');
        resumeStorage.addPart(getFileKey(file), 'u1', {
            ETag: 'e1',
            PartNumber: 1,
        });
        const upload = vi.fn(async (_f: File, o: FileUploadOptions = {}) => ({
            uploadId: o.resume!.uploadId,
            parts: o.resume!.parts,
        }));

        await createUploadFile({
            targetId: 't1',
            resumeStorage,
            upload,
            complete: async () => ({id: 'a'}),
        })(file, ctx);

        expect(upload.mock.calls[0][1]?.resume).toEqual({
            uploadId: 'u1',
            parts: [{ETag: 'e1', PartNumber: 1}],
        });
    });

    it('starts over when the storage no longer knows the upload', async () => {
        const resumeStorage = new ResumeStorage('alice', memoryStorage());
        resumeStorage.start(getFileKey(file), 'gone');
        resumeStorage.addPart(getFileKey(file), 'gone', {
            ETag: 'e1',
            PartNumber: 1,
        });
        const upload = vi
            .fn()
            .mockRejectedValueOnce(new ApiError('Not found', 404, {}))
            .mockResolvedValueOnce({uploadId: 'u2', parts: []});

        await createUploadFile({
            targetId: 't1',
            resumeStorage,
            upload,
            complete: async () => ({id: 'a'}),
        })(file, ctx);

        expect(upload).toHaveBeenCalledTimes(2);
        expect(upload.mock.calls[1][1].resume).toBeUndefined();
    });
});
