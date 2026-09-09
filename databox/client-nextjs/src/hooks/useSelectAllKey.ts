import {useEffect, useRef} from 'react';

const stack: symbol[] = [];

/**
 * Ctrl/Cmd+A handler. Only the most recently mounted listener reacts so a
 * dialog list takes precedence over the list behind it. Ignored when the focus
 * is in an editable field.
 */
export function useSelectAllKey(handler: () => void, enabled = true): void {
    const handlerRef = useRef(handler);
    handlerRef.current = handler;

    useEffect(() => {
        if (!enabled) {
            return;
        }
        const token = Symbol('select-all');
        stack.push(token);

        const onKeyDown = (e: KeyboardEvent) => {
            if (stack[stack.length - 1] !== token) {
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
            const i = stack.indexOf(token);
            if (i >= 0) {
                stack.splice(i, 1);
            }
        };
    }, [enabled]);
}
