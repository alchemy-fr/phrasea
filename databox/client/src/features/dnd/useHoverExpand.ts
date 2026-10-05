import {useEffect} from 'react';

/**
 * Expands a collapsed tree node hovered for a while during a drag, so the
 * user can reach its children.
 */
export function useHoverExpand(
    hovering: boolean,
    expanded: boolean,
    expand: () => void,
    delay = 600
): void {
    useEffect(() => {
        if (!hovering || expanded) {
            return;
        }
        const timer = setTimeout(expand, delay);

        return () => clearTimeout(timer);
    }, [hovering, expanded, expand, delay]);
}
