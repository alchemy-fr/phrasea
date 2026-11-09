import {describe, expect, it} from 'vitest';
import {checkBatch, isTooLarge, mergeSelection} from './limits';

const file = (name: string, size: number) =>
    new File(['x'.repeat(size)], name, {lastModified: 1});

describe('checkBatch', () => {
    it('accepts a batch within the limits', () => {
        expect(
            checkBatch([{size: 5}, {size: 5}], {
                maxCommitSize: 10,
                maxFileCount: 2,
            })
        ).toEqual([]);
    });

    it('reports a batch too large or with too many files', () => {
        expect(
            checkBatch([{size: 6}, {size: 6}], {
                maxCommitSize: 10,
                maxFileCount: 1,
            })
        ).toEqual([
            {type: 'too_many_files', count: 2, max: 1},
            {type: 'too_large_batch', size: 12, max: 10},
        ]);
    });

    it('has no limit when none is configured', () => {
        expect(checkBatch([{size: 1e12}], {})).toEqual([]);
    });
});

describe('isTooLarge', () => {
    it('compares to the maximum file size', () => {
        expect(isTooLarge({size: 11}, {maxFileSize: 10})).toBe(true);
        expect(isTooLarge({size: 10}, {maxFileSize: 10})).toBe(false);
        expect(isTooLarge({size: 10}, {})).toBe(false);
    });
});

describe('mergeSelection', () => {
    it('adds the dropped files, once', () => {
        const a = file('a', 1);
        const b = file('b', 1);
        const {files, duplicates} = mergeSelection(
            [a],
            [file('a', 1), b, file('b', 1)],
            {}
        );
        expect(files.map(f => f.name)).toEqual(['a', 'b']);
        expect(duplicates).toBe(2);
    });

    it('replaces the selection in single file mode', () => {
        const {files, duplicates} = mergeSelection(
            [file('a', 1)],
            [file('b', 1)],
            {maxFileCount: 1}
        );
        expect(files.map(f => f.name)).toEqual(['b']);
        expect(duplicates).toBe(0);
    });
});
