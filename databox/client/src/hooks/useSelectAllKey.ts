import {useEffect, useRef} from 'react';

type Entry = {token: symbol; priority: number};

const stack: Entry[] = [];

function isActive(token: symbol): boolean {
    let active: Entry | undefined;
    for (const entry of stack) {
        if (!active || entry.priority >= active.priority) {
            active = entry;
        }
    }

    return active?.token === token;
}

/**
 * Ctrl/Cmd+A handler. Only one listener reacts: the one with the highest
 * `priority`, the most recently mounted one among equals, so a dialog list
 * takes precedence over the list behind it. A full-screen overlay passes a
 * higher priority so the list behind it cannot take the key back by
 * remounting. Ignored when the focus is in an editable field.
 */
export function useSelectAllKey(
    handler: () => void,
    enabled = true,
    priority = 0
): void {
    const handlerRef = useRef(handler);
    handlerRef.current = handler;

    useEffect(() => {
        if (!enabled) {
            return;
        }
        const token = Symbol('select-all');
        stack.push({token, priority});

        const onKeyDown = (e: KeyboardEvent) => {
            if (!isActive(token)) {
                return;
            }
            if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'a') {
                return;
            }
            const el = document.activeElement as HTMLElement | null;
            if (
                el &&
                (el.isContentEditable ||
                    (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) &&
                        (el as HTMLInputElement).type !== 'checkbox'))
            ) {
                return;
            }
            e.preventDefault();
            handlerRef.current();
        };
        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            const i = stack.findIndex(entry => entry.token === token);
            if (i >= 0) {
                stack.splice(i, 1);
            }
        };
    }, [enabled, priority]);
}
