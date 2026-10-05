import type {Ref, RefCallback} from 'react';

type RefCleanup = (() => void) | void;

function setRef<T>(ref: Ref<T> | undefined | null, node: T | null): RefCleanup {
    if (typeof ref === 'function') {
        return ref(node) as RefCleanup;
    }
    if (ref) {
        ref.current = node;
    }
}

/**
 * One callback ref feeding several refs (a Radix `asChild` ref and a
 * dnd-kit node ref on the same element, for instance). Missing refs are
 * skipped: a Slot only passes a ref when its parent set one. A callback ref
 * may return a cleanup (React 19): it then runs on unmount in place of the
 * call with null.
 */
export function mergeRefs<T>(
    ...refs: (Ref<T> | undefined | null)[]
): RefCallback<T> {
    return node => {
        const cleanups = refs.map(ref => setRef(ref, node));

        return () => {
            cleanups.forEach((cleanup, i) => {
                if (typeof cleanup === 'function') {
                    cleanup();
                } else {
                    setRef(refs[i], null);
                }
            });
        };
    };
}
