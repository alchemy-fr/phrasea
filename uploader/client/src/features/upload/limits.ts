import type {UploadConfig} from '@/lib/config/types';

export type BatchIssue =
    | {type: 'too_many_files'; count: number; max: number}
    | {type: 'too_large_batch'; size: number; max: number};

/** What prevents the batch from being sent, also checked by the API on commit */
export function checkBatch(
    files: {size: number}[],
    {
        maxCommitSize,
        maxFileCount,
    }: Pick<UploadConfig, 'maxCommitSize' | 'maxFileCount'>
): BatchIssue[] {
    const issues: BatchIssue[] = [];
    if (maxFileCount && files.length > maxFileCount) {
        issues.push({
            type: 'too_many_files',
            count: files.length,
            max: maxFileCount,
        });
    }
    const size = totalSize(files);
    if (maxCommitSize && size > maxCommitSize) {
        issues.push({type: 'too_large_batch', size, max: maxCommitSize});
    }

    return issues;
}

export function totalSize(files: {size: number}[]): number {
    return files.reduce((sum, f) => sum + f.size, 0);
}

export function isTooLarge(
    file: {size: number},
    {maxFileSize}: Pick<UploadConfig, 'maxFileSize'>
): boolean {
    return !!maxFileSize && file.size > maxFileSize;
}

/**
 * The selection after a drop: added to the current one, or replacing it in
 * single file mode. A file dropped twice is kept once.
 */
export function mergeSelection(
    current: File[],
    dropped: File[],
    {maxFileCount}: Pick<UploadConfig, 'maxFileCount'>
): File[] {
    if (maxFileCount === 1) {
        return dropped.slice(-1);
    }
    const key = (f: File) => `${f.name}:${f.size}:${f.lastModified}`;
    const known = new Set(current.map(key));

    return [...current, ...dropped.filter(f => !known.has(key(f)))];
}
