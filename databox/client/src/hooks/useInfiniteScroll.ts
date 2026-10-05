import {useEffect, useRef} from 'react';

/**
 * Calls `onReachEnd` when the sentinel element becomes visible inside `root`.
 */
export function useInfiniteScroll(
    root: React.RefObject<HTMLElement | null>,
    onReachEnd: (() => void) | undefined,
    enabled: boolean
) {
    const sentinelRef = useRef<HTMLDivElement | null>(null);
    const cb = useRef(onReachEnd);
    cb.current = onReachEnd;

    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!sentinel || !enabled) {
            return;
        }
        const observer = new IntersectionObserver(
            entries => {
                if (entries.some(e => e.isIntersecting)) {
                    cb.current?.();
                }
            },
            {root: root.current, rootMargin: '200px 0px'}
        );
        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [root, enabled]);

    return sentinelRef;
}
