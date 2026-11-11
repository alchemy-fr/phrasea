'use client';

import {useMemo} from 'react';
import {useConfig} from '@/lib/config/ConfigProvider';
import type {UploadConfig} from '@/lib/config/types';
import type {UploadLinkSession} from '@/types/api';
import {useUploadAccess} from './UploadAccessContext';

export type UploadLimits = UploadConfig & {
    /** `maxFileCount` is what is left on the upload link */
    fileCountFromLink: boolean;
    /** `maxCommitSize` is what is left on the upload link */
    commitSizeFromLink: boolean;
    /** Nothing more can be sent through the upload link */
    exhausted: boolean;
};

type LinkUsage = Pick<
    UploadLinkSession,
    'maxAssets' | 'usedAssets' | 'maxTotalSize' | 'usedSize'
>;

/** The lower of two optional limits */
function lowest(
    global: number | undefined,
    left: number | null
): {value: number | undefined; fromLink: boolean} {
    if (left === null) {
        return {value: global, fromLink: false};
    }
    if (global !== undefined && global <= left) {
        return {value: global, fromLink: false};
    }

    return {value: left, fromLink: true};
}

/**
 * The limits of a batch: the ones of the uploader, lowered by what is left
 * on the upload link (its limits count every commit made with it).
 */
export function computeUploadLimits(
    upload: UploadConfig,
    link?: LinkUsage
): UploadLimits {
    const filesLeft =
        typeof link?.maxAssets === 'number'
            ? Math.max(0, link.maxAssets - link.usedAssets)
            : null;
    const sizeLeft =
        typeof link?.maxTotalSize === 'number'
            ? Math.max(0, link.maxTotalSize - link.usedSize)
            : null;
    const fileCount = lowest(upload.maxFileCount, filesLeft);
    const commitSize = lowest(upload.maxCommitSize, sizeLeft);

    return {
        ...upload,
        maxFileCount: fileCount.value,
        maxCommitSize: commitSize.value,
        fileCountFromLink: fileCount.fromLink,
        commitSizeFromLink: commitSize.fromLink,
        exhausted: filesLeft === 0 || sizeLeft === 0,
    };
}

/** The limits of the batch being prepared, see {@link computeUploadLimits} */
export function useUploadLimits(): UploadLimits {
    const {upload} = useConfig();
    const {link} = useUploadAccess();

    return useMemo(() => computeUploadLimits(upload, link), [upload, link]);
}
