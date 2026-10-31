import type {FacetBucket} from '@/types/api';
import {resolveBucket} from './buckets';

export type BucketNode = {
    bucket: FacetBucket;
    value: string;
    children: BucketNode[];
};

/**
 * Nests the buckets of an entity facet under their parent (`item.parentId`,
 * set for the entities of a hierarchical list). A bucket whose parent is not
 * among the buckets stays at the top. The order of the buckets is kept.
 */
export function buildBucketTree(buckets: FacetBucket[]): BucketNode[] {
    const nodes = new Map<string, BucketNode>();
    for (const bucket of buckets) {
        const value = String(resolveBucket(bucket).value);
        nodes.set(value, {bucket, value, children: []});
    }

    const roots: BucketNode[] = [];
    for (const node of nodes.values()) {
        const parent = nodes.get(getParentId(node.bucket) ?? '');
        if (parent && parent !== node) {
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    }

    return roots;
}

export function isBucketTree(nodes: BucketNode[]): boolean {
    return nodes.some(n => n.children.length > 0);
}

/** The node or one of its descendants matches */
export function someInBranch(
    node: BucketNode,
    predicate: (value: string) => boolean
): boolean {
    return (
        predicate(node.value) ||
        node.children.some(c => someInBranch(c, predicate))
    );
}

function getParentId(bucket: FacetBucket): string | undefined {
    const parentId = resolveBucket(bucket).item?.parentId;

    return typeof parentId === 'string' ? parentId : undefined;
}
