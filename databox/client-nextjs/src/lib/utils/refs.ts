import type {Ref, RefCallback} from 'react';

/**
 * One callback ref feeding several refs (a Radix `asChild` ref and a
 * dnd-kit node ref on the same element, for instance). Missing refs are
 * skipped: a Slot only passes a ref when its parent set one.
 */
export function mergeRefs<T>(
    ...refs: (Ref<T> | undefined | null)[]
): RefCallback<T> {
    return node => {
        for (const ref of refs) {
            if (typeof ref === 'function') {
                ref(node);
            } else if (ref) {
                ref.current = node;
            }
        }
    };
}
