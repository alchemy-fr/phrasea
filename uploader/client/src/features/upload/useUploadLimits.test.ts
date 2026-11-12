import {describe, expect, it} from 'vitest';
import {computeUploadLimits} from './useUploadLimits';

const upload = {
    maxFileSize: 1000,
    maxCommitSize: 500,
    maxFileCount: 10,
    allowedTypes: {},
};

const link = (
    usage: Partial<{
        maxAssets: number | null;
        usedAssets: number;
        maxTotalSize: number | null;
        usedSize: number;
    }>
) => ({
    maxAssets: null,
    usedAssets: 0,
    maxTotalSize: null,
    usedSize: 0,
    ...usage,
});

describe('computeUploadLimits', () => {
    it('keeps the limits of the uploader without a link', () => {
        expect(computeUploadLimits(upload)).toEqual({
            ...upload,
            maxFileSize: 500,
            fileCountFromLink: false,
            commitSizeFromLink: false,
            fileSizeFromBatch: true,
            exhausted: false,
        });
    });

    it('keeps the limits of the uploader for a link without limits', () => {
        const limits = computeUploadLimits(upload, link({usedAssets: 50}));

        expect(limits.maxFileCount).toBe(10);
        expect(limits.maxCommitSize).toBe(500);
        expect(limits.exhausted).toBe(false);
    });

    it('lowers them to what is left on the link', () => {
        const limits = computeUploadLimits(
            upload,
            link({
                maxAssets: 5,
                usedAssets: 2,
                maxTotalSize: 800,
                usedSize: 600,
            })
        );

        expect(limits.maxFileCount).toBe(3);
        expect(limits.fileCountFromLink).toBe(true);
        expect(limits.maxCommitSize).toBe(200);
        expect(limits.commitSizeFromLink).toBe(true);
        expect(limits.maxFileSize).toBe(200);
        expect(limits.fileSizeFromBatch).toBe(true);
    });

    it('keeps the uploader limits when they are the lowest', () => {
        const limits = computeUploadLimits(
            upload,
            link({maxAssets: 100, maxTotalSize: 10_000})
        );

        expect(limits.maxFileCount).toBe(10);
        expect(limits.fileCountFromLink).toBe(false);
        expect(limits.maxCommitSize).toBe(500);
        expect(limits.commitSizeFromLink).toBe(false);
    });

    it('applies the link when the uploader has no limit', () => {
        const limits = computeUploadLimits(
            {allowedTypes: {}},
            link({maxAssets: 4, usedAssets: 1})
        );

        expect(limits.maxFileCount).toBe(3);
        expect(limits.maxCommitSize).toBeUndefined();
    });

    it('keeps the file size when it is the lowest', () => {
        const limits = computeUploadLimits(
            {...upload, maxFileSize: 100},
            link({maxTotalSize: 800, usedSize: 600})
        );

        expect(limits.maxFileSize).toBe(100);
        expect(limits.fileSizeFromBatch).toBe(false);
    });

    it('keeps the file size when it equals the size of an upload', () => {
        const limits = computeUploadLimits({...upload, maxFileSize: 500});

        expect(limits.maxFileSize).toBe(500);
        expect(limits.fileSizeFromBatch).toBe(false);
    });

    it('caps the file size to what is left on the link', () => {
        const limits = computeUploadLimits(
            {allowedTypes: {}},
            link({maxTotalSize: 800, usedSize: 600})
        );

        expect(limits.maxFileSize).toBe(200);
        expect(limits.fileSizeFromBatch).toBe(true);
    });

    it('has no file size without any size limit', () => {
        const limits = computeUploadLimits(
            {allowedTypes: {}},
            link({maxAssets: 4})
        );

        expect(limits.maxFileSize).toBeUndefined();
        expect(limits.fileSizeFromBatch).toBe(false);
    });

    it.each([
        [{maxAssets: 2, usedAssets: 2}],
        [{maxAssets: 2, usedAssets: 3}],
        [{maxTotalSize: 100, usedSize: 100}],
    ])('is exhausted when nothing is left (%o)', usage => {
        const limits = computeUploadLimits(upload, link(usage));

        expect(limits.exhausted).toBe(true);
    });
});
