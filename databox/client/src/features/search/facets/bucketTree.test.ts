import {describe, expect, it} from 'vitest';
import type {FacetBucket} from '@/types/api';
import {buildBucketTree, isBucketTree, someInBranch} from './bucketTree';

function bucket(id: string, parentId: string | null, count = 1): FacetBucket {
    return {
        key: {value: id, label: id, item: {id, parentId}},
        doc_count: count,
    };
}

describe('buildBucketTree', () => {
    it('nests the buckets under their parent, in their order', () => {
        const tree = buildBucketTree([
            bucket('europe', null, 3),
            bucket('france', 'europe', 2),
            bucket('paris', 'france'),
            bucket('italie', 'europe'),
        ]);

        expect(tree.map(n => n.value)).toEqual(['europe']);
        expect(tree[0].children.map(n => n.value)).toEqual([
            'france',
            'italie',
        ]);
        expect(tree[0].children[0].children.map(n => n.value)).toEqual([
            'paris',
        ]);
        expect(isBucketTree(tree)).toBe(true);
    });

    it('keeps at the top a bucket whose parent is missing', () => {
        const tree = buildBucketTree([
            bucket('paris', 'france'),
            bucket('rome', 'latium'),
        ]);

        expect(tree.map(n => n.value)).toEqual(['paris', 'rome']);
        expect(isBucketTree(tree)).toBe(false);
    });

    it('leaves the buckets of a flat list flat', () => {
        const tree = buildBucketTree([
            {key: 'a', doc_count: 2},
            {key: 'b', doc_count: 1},
        ]);

        expect(tree.map(n => n.value)).toEqual(['a', 'b']);
        expect(isBucketTree(tree)).toBe(false);
    });

    it('finds a value in a branch', () => {
        const [europe] = buildBucketTree([
            bucket('europe', null),
            bucket('france', 'europe'),
            bucket('paris', 'france'),
        ]);

        expect(someInBranch(europe, v => v === 'paris')).toBe(true);
        expect(someInBranch(europe.children[0], v => v === 'europe')).toBe(
            false
        );
    });
});
