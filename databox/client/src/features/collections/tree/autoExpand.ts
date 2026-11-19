import {useEffect, useRef} from 'react';
import type {CollectionNode, Pager} from '../collectionStore';

/**
 * Whether a fully loaded children list holds exactly one collection.
 * The hint pager built from `children` is capped and has no total: it is not
 * trusted until the children are loaded.
 */
export function isOnlyChildPager(pager?: Pager): boolean {
    return !!pager?.loaded && !pager.next && pager.ids.length === 1;
}

/**
 * The list endpoint ships the first children along with each collection, so an
 * empty `children` means a leaf. Without that hint (search results,
 * ascendants…) we can't tell, and the node is assumed to have children.
 */
export function mayHaveChildren(node: CollectionNode, pager?: Pager): boolean {
    const childrenKnown = !!pager?.loaded || Array.isArray(node.children);

    return childrenKnown ? (pager?.ids.length ?? 0) > 0 : true;
}

/**
 * Calls `expand` the first time `shouldExpand` becomes true, at most once per
 * mount: once the user collapses the node, it stays collapsed until it is
 * mounted again.
 */
export function useAutoExpand(shouldExpand: boolean, expand: () => void): void {
    const done = useRef(false);

    useEffect(() => {
        if (shouldExpand && !done.current) {
            done.current = true;
            expand();
        }
    }, [shouldExpand, expand]);
}
