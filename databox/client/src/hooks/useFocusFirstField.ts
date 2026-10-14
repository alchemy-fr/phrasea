import {RefObject, useEffect} from 'react';

const FIELDS = [
    'input:not([type=hidden]):not([disabled])',
    'textarea:not([disabled])',
    '[role=combobox]:not([disabled])',
].join(',');

/**
 * Focuses the first field inside `container` whenever `active` turns true
 * (e.g. a creation form showing up), unless the focus is already in it.
 */
export function useFocusFirstField(
    container: RefObject<HTMLElement | null>,
    active: boolean
): void {
    useEffect(() => {
        if (!active) {
            return;
        }
        // After the form (and what it renders in effects) is laid out
        const frame = requestAnimationFrame(() => {
            const root = container.current;
            if (!root || root.contains(document.activeElement)) {
                return;
            }
            root.querySelector<HTMLElement>(FIELDS)?.focus();
        });

        return () => cancelAnimationFrame(frame);
    }, [container, active]);
}
